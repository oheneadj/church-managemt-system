<?php

declare(strict_types=1);

use App\Actions\SmsCredits\StartSmsCreditPurchaseAction;
use App\DTOs\SmsCredits\StartSmsCreditPurchaseDTO;
use App\Enums\MomoNetwork;
use App\Enums\SmsCreditPurchaseStatus;
use App\Exceptions\PaymentProviderUnavailableException;
use App\Jobs\StartSmsCreditPurchaseJob;
use App\Models\Church;
use App\Models\SmsCreditLedger;
use App\Models\SmsCreditPurchase;
use App\Models\SmsPackage;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

// Buying SMS credits, the church's side (D-069 step c2, QA-08 TC-08-13, docs/08 SMS Credits): the packages on sale, starting a MoMo
// payment for one, and reading how it went. Only the church admin (`church.settings`) may. Starting records a pending purchase and
// queues the provider call; a worker asks the provider, and the settling (tested in SettleSmsCreditPurchaseTest) adds the credits.

const CREDIT_PACKAGES_URL = 'http://grace.localhost/api/v1/sms-credits/packages';
const CREDIT_PURCHASES_URL = 'http://grace.localhost/api/v1/sms-credits/purchases';
const START_PPZ_BASE = 'https://ppz-start.test/api/v1';

/** A staff member of the church with one role. */
function creditStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

/** Starts a purchase with a fresh Idempotency-Key unless one is given. */
function startCreditPurchase(array $body = [], ?string $key = null): TestResponse
{
    return test()->postJson(CREDIT_PURCHASES_URL, [...['sms_package_id' => test()->package->id, 'phone' => '0244123456', 'network' => 'mtn'], ...$body], ['Idempotency-Key' => $key ?? 'purchase-'.Str::random(20)]);
}

/** A PayPerlez reply to the collection call. */
function startCollectReply(SmsCreditPurchase $purchase, string $status, ?float $amount = null, int $code = 201): Illuminate\Http\Client\PromiseInterface|PromiseInterface
{
    return Http::response(['success' => true, 'data' => ['reference' => 'ppz-start-1', 'status' => $status, 'amount' => $amount ?? (float) $purchase->amount, 'fee' => null, 'received' => null, 'client_reference' => $purchase->id], 'meta' => ['request_id' => 'r']], $code);
}

beforeEach(function (): void {
    Queue::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace', 'sms_credits' => 10]);
    $this->other = Church::factory()->create(['slug' => 'other', 'sms_credits' => 10]);
    $this->admin = creditStaff($this->church, 'church-admin');
    $this->package = SmsPackage::factory()->create(['name' => 'Bundle 50', 'credits' => 50, 'price' => '12.50', 'sort_order' => 1]);
    Sanctum::actingAs($this->admin, ['*']);
    RateLimiter::clear('credit-purchase');
});

describe('the packages on sale', function (): void {
    it('lists only the packages that are switched on, in the owner\'s order, with exact prices', function (): void {
        SmsPackage::factory()->create(['name' => 'Bundle 10', 'credits' => 10, 'price' => '3.00', 'sort_order' => 0]);
        SmsPackage::factory()->create(['name' => 'Old bundle', 'credits' => 5, 'price' => '1.00', 'is_active' => false]);

        $response = $this->getJson(CREDIT_PACKAGES_URL)->assertOk();

        expect(collect($response->json('data'))->pluck('name')->all())->toBe(['Bundle 10', 'Bundle 50'])
            ->and($response->json('data.1'))->toBe(['id' => $this->package->id, 'name' => 'Bundle 50', 'credits' => 50, 'price' => '12.50', 'price_formatted' => 'GHS 12.50']);
    });

    it('is empty when nothing is on sale', function (): void {
        SmsPackage::query()->update(['is_active' => false]);

        $this->getJson(CREDIT_PACKAGES_URL)->assertOk()->assertExactJson(['data' => []]);
    });

    it('is refused to anyone not signed in and to roles that do not manage the church', function (): void {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(creditStaff($this->church, 'finance-secretary'), ['*']);
        $this->getJson(CREDIT_PACKAGES_URL)->assertForbidden();
    });

    it('is refused without signing in', function (): void {
        $this->app['auth']->forgetGuards();
        $this->withHeaders([])->getJson(CREDIT_PACKAGES_URL, ['Authorization' => ''])->assertUnauthorized();
    });
});

describe('starting a purchase', function (): void {
    it('answers 202 with a pending purchase, copies the package, and queues the provider call on the payments queue', function (): void {
        $response = startCreditPurchase()->assertStatus(202);

        $purchase = SmsCreditPurchase::query()->sole();
        expect($response->json('data'))->toMatchArray(['id' => $purchase->id, 'status' => 'pending', 'credits' => 50, 'amount' => '12.50', 'amount_formatted' => 'GHS 12.50', 'network' => 'mtn', 'failure_reason' => null, 'completed_at' => null])
            ->and($purchase->church_id)->toBe($this->church->id)->and($purchase->user_id)->toBe($this->admin->id)->and($purchase->sms_package_id)->toBe($this->package->id)
            ->and($purchase->phone)->toBe('+233244123456')->and($purchase->network)->toBe(MomoNetwork::Mtn)->and($purchase->status)->toBe(SmsCreditPurchaseStatus::Pending)
            ->and($response->getContent())->not->toContain('233244123456');
        Queue::assertPushedOn('payments', StartSmsCreditPurchaseJob::class, fn (StartSmsCreditPurchaseJob $job): bool => $job->purchaseId === $purchase->id);
    });

    it('adds no credits and charges nothing itself', function (): void {
        Http::fake();

        startCreditPurchase()->assertStatus(202);

        expect($this->church->fresh()->sms_credits)->toBe(10)->and(SmsCreditLedger::query()->count())->toBe(0);
        Http::assertNothingSent();
    });

    it('is not changed for a church that already paid when the package price changes later', function (): void {
        startCreditPurchase()->assertStatus(202);
        $this->package->update(['price' => '99.00', 'credits' => 1]);

        expect(SmsCreditPurchase::query()->sole())->amount->toBe('12.50')->credits->toBe(50);
    });

    it('starts one purchase for one Idempotency-Key, however many times it is sent', function (): void {
        $first = startCreditPurchase(key: 'same-key-12345')->assertStatus(202);
        $again = startCreditPurchase(key: 'same-key-12345')->assertStatus(202);

        expect(SmsCreditPurchase::query()->count())->toBe(1)->and($again->json('data.id'))->toBe($first->json('data.id'));
        Queue::assertPushed(StartSmsCreditPurchaseJob::class, 1);
    });

    it('needs an Idempotency-Key', function (): void {
        $this->postJson(CREDIT_PURCHASES_URL, ['sms_package_id' => $this->package->id, 'phone' => '0244123456', 'network' => 'mtn'])->assertStatus(400);

        expect(SmsCreditPurchase::query()->count())->toBe(0);
    });

    it('is refused with 422 for bad input and records nothing', function (array $body, string $field): void {
        startCreditPurchase($body)->assertUnprocessable()->assertJsonValidationErrors($field);

        expect(SmsCreditPurchase::query()->count())->toBe(0);
        Queue::assertNothingPushed();
    })->with([
        'no package' => [['sms_package_id' => null], 'sms_package_id'],
        'a package that does not exist' => [['sms_package_id' => '01HZZZZZZZZZZZZZZZZZZZZZZZ'], 'sms_package_id'],
        'not a ulid' => [['sms_package_id' => 'bundle'], 'sms_package_id'],
        'no phone' => [['phone' => null], 'phone'],
        'a phone that is not a number' => [['phone' => 'call me'], 'phone'],
        'no network' => [['network' => null], 'network'],
        'an unknown network' => [['network' => 'airtel'], 'network'],
    ]);

    it('refuses a package that is switched off', function (): void {
        $this->package->update(['is_active' => false]);

        startCreditPurchase()->assertUnprocessable()->assertJsonValidationErrors('sms_package_id');
    });

    it('refuses a package that was switched off after the request was checked', function (): void {
        $action = app(StartSmsCreditPurchaseAction::class);
        $this->package->update(['is_active' => false]);

        expect(fn () => $action->execute(new StartSmsCreditPurchaseDTO($this->admin, $this->church, $this->package->id, '+233244123456', MomoNetwork::Mtn)))
            ->toThrow(ValidationException::class);
    });

    it('is refused to roles that do not manage the church', function (string $role): void {
        Sanctum::actingAs(creditStaff($this->church, $role), ['*']);

        startCreditPurchase()->assertForbidden();

        expect(SmsCreditPurchase::query()->count())->toBe(0);
    })->with(['finance-secretary', 'pastor', 'department-head', 'youth-pastor']);

    it('is refused without signing in', function (): void {
        $this->app['auth']->forgetGuards();

        $this->postJson(CREDIT_PURCHASES_URL, [], ['Idempotency-Key' => 'purchase-unsigned-1', 'Authorization' => ''])->assertUnauthorized();
    });

    it('is limited to a few starts an hour for each church', function (): void {
        config(['limits.credit_purchases_per_hour' => 2]);

        startCreditPurchase()->assertStatus(202);
        startCreditPurchase()->assertStatus(202);
        startCreditPurchase()->assertStatus(429);
    });
});

describe('reading a purchase', function (): void {
    it('shows the purchase of the church', function (): void {
        $purchase = SmsCreditPurchase::factory()->create(['church_id' => $this->church->id, 'user_id' => $this->admin->id, 'credits' => 50, 'amount' => '12.50']);

        $this->getJson(CREDIT_PURCHASES_URL.'/'.$purchase->id)->assertOk()->assertJsonPath('data.id', $purchase->id)->assertJsonPath('data.status', 'pending')->assertJsonPath('data.amount', '12.50');
    });

    it('shows a failed purchase with its reason', function (): void {
        $purchase = SmsCreditPurchase::factory()->create(['church_id' => $this->church->id, 'user_id' => $this->admin->id, 'status' => SmsCreditPurchaseStatus::Failed, 'failure_reason' => 'The payment was not completed.']);

        $this->getJson(CREDIT_PURCHASES_URL.'/'.$purchase->id)->assertOk()->assertJsonPath('data.status', 'failed')->assertJsonPath('data.failure_reason', 'The payment was not completed.');
    });

    it('is a 404 for another church\'s purchase (isolation)', function (): void {
        $theirs = SmsCreditPurchase::factory()->create(['church_id' => $this->other->id]);

        $this->getJson(CREDIT_PURCHASES_URL.'/'.$theirs->id)->assertNotFound();
    });

    it('is a 404 for a purchase that does not exist', function (): void {
        $this->getJson(CREDIT_PURCHASES_URL.'/01HZZZZZZZZZZZZZZZZZZZZZZZ')->assertNotFound();
    });

    it('is refused to roles that do not manage the church', function (): void {
        $purchase = SmsCreditPurchase::factory()->create(['church_id' => $this->church->id]);
        Sanctum::actingAs(creditStaff($this->church, 'finance-secretary'), ['*']);

        $this->getJson(CREDIT_PURCHASES_URL.'/'.$purchase->id)->assertForbidden();
    });

    it('is refused without signing in', function (): void {
        $purchase = SmsCreditPurchase::factory()->create(['church_id' => $this->church->id]);
        $this->app['auth']->forgetGuards();

        $this->getJson(CREDIT_PURCHASES_URL.'/'.$purchase->id, ['Authorization' => ''])->assertUnauthorized();
    });
});

describe('the job that asks the provider to collect', function (): void {
    beforeEach(function (): void {
        config(['services.payperlez.api_key' => 'ppz_test_key', 'services.payperlez.base_url' => START_PPZ_BASE]);
        $this->purchase = SmsCreditPurchase::factory()->create(['church_id' => $this->church->id, 'user_id' => $this->admin->id, 'credits' => 50, 'amount' => '12.50', 'phone' => '+233244123456', 'network' => MomoNetwork::Mtn]);
    });

    it('asks for exactly the amount, the number and network, with our purchase id as the reference, and keeps the provider\'s reference', function (): void {
        Http::fake([START_PPZ_BASE.'/collections' => startCollectReply($this->purchase, 'pending')]);

        app()->call([new StartSmsCreditPurchaseJob($this->purchase->id), 'handle']);

        Http::assertSent(fn ($request): bool => str_contains($request->body(), '"amount":12.50') && $request['client_reference'] === $this->purchase->id && $request['network'] === 'mtn' && str_contains($request['customer_msisdn'], '244123456'));
        $fresh = $this->purchase->fresh();
        expect($fresh->status)->toBe(SmsCreditPurchaseStatus::Pending)->and($fresh->provider_reference)->toBe('ppz-start-1')->and($this->church->fresh()->sms_credits)->toBe(10);
    });

    it('adds the credits at once when the provider answers that it is already paid', function (): void {
        Http::fake([START_PPZ_BASE.'/collections' => startCollectReply($this->purchase, 'succeeded')]);

        app()->call([new StartSmsCreditPurchaseJob($this->purchase->id), 'handle']);

        expect($this->purchase->fresh()->status)->toBe(SmsCreditPurchaseStatus::Completed)->and($this->church->fresh()->sms_credits)->toBe(60);
    });

    it('fails the purchase, charging nothing, when the provider refuses for good', function (): void {
        Http::fake([START_PPZ_BASE.'/collections' => Http::response(['success' => false, 'error' => ['code' => 'invalid_msisdn']], 422)]);
        Log::spy();

        app()->call([new StartSmsCreditPurchaseJob($this->purchase->id), 'handle']);

        $fresh = $this->purchase->fresh();
        expect($fresh->status)->toBe(SmsCreditPurchaseStatus::Failed)->and($fresh->provider_reference)->toBeNull()->and($fresh->failure_reason)->not->toBeNull()->and($this->church->fresh()->sms_credits)->toBe(10);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'The provider refused to start an SMS credit purchase' && $context === ['purchase_id' => $this->purchase->id, 'church_id' => $this->church->id]);
    });

    it('lets a busy provider be tried again, leaving the purchase pending', function (): void {
        Http::fake([START_PPZ_BASE.'/collections' => Http::response('', 503)]);

        expect(fn () => app()->call([new StartSmsCreditPurchaseJob($this->purchase->id), 'handle']))->toThrow(PaymentProviderUnavailableException::class);

        expect($this->purchase->fresh()->status)->toBe(SmsCreditPurchaseStatus::Pending);
    });

    it('asks nothing for a purchase already asked about, settled or missing', function (): void {
        Http::fake();
        $asked = SmsCreditPurchase::factory()->create(['church_id' => $this->church->id, 'provider_reference' => 'ppz-already']);
        $done = SmsCreditPurchase::factory()->create(['church_id' => $this->church->id, 'status' => SmsCreditPurchaseStatus::Completed]);

        foreach ([$asked->id, $done->id, '01HZZZZZZZZZZZZZZZZZZZZZZZ'] as $id) {
            app()->call([new StartSmsCreditPurchaseJob($id), 'handle']);
        }

        Http::assertNothingSent();
    });

    it('logs without anything personal when the tries run out', function (): void {
        Log::spy();

        (new StartSmsCreditPurchaseJob($this->purchase->id))->failed(new RuntimeException('x'));

        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context): bool => $message === 'An SMS credit purchase could not be started' && $context === ['purchase_id' => $this->purchase->id, 'error' => RuntimeException::class]);
    });
});

describe('the policy, as the last line of defence', function (): void {
    it('lets only a church admin of the same church read a purchase, whatever the route binding does', function (): void {
        $mine = SmsCreditPurchase::factory()->create(['church_id' => $this->church->id]);
        $theirs = SmsCreditPurchase::factory()->create(['church_id' => $this->other->id]);
        $finance = creditStaff($this->church, 'finance-secretary');

        expect($this->admin->can('view', $mine))->toBeTrue()->and($this->admin->can('view', $theirs))->toBeFalse()->and($finance->can('view', $mine))->toBeFalse()
            ->and($this->admin->can('create', SmsCreditPurchase::class))->toBeTrue()->and($finance->can('create', SmsCreditPurchase::class))->toBeFalse();
    });
});
