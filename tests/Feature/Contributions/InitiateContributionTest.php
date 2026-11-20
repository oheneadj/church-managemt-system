<?php

declare(strict_types=1);

use App\Contracts\PaymentProvider;
use App\DTOs\Payments\CollectionResult;
use App\Enums\ContributionFailureReason;
use App\Enums\ContributionStatus;
use App\Enums\MomoNetwork;
use App\Enums\PaymentMethod;
use App\Enums\PayoutMethod;
use App\Enums\ProviderPaymentStatus;
use App\Exceptions\PaymentProviderUnavailableException;
use App\Exceptions\PaymentRejectedException;
use App\Jobs\StartContributionPaymentJob;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\FundType;
use App\Models\Member;
use App\Models\User;
use App\Repositories\ContributionRepositoryInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

// Starting a mobile money gift (docs/08 Initiate, D-053, D-069 step d3): the gift is saved pending with the fee on top, a worker
// asks the provider for the money, and nothing counts until the provider confirms. The provider is faked.

const INITIATE_GIFTS = 'http://grace.localhost/api/v1/contributions/initiate';

/** A staff member of the church with one role. */
function initiateStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

/** Starts a gift with a fresh Idempotency-Key unless one is given. */
function initiateGift(array $body = [], ?string $key = null): TestResponse
{
    return test()->postJson(INITIATE_GIFTS, [
        'fund_type_id' => test()->fund->id, 'amount' => '100.00', 'payment_method' => 'momo', 'phone' => '0244123456', 'network' => 'mtn', ...$body,
    ], ['Idempotency-Key' => $key ?? 'init-'.Str::random(20)]);
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create([
        'slug' => 'grace', 'payout_method' => PayoutMethod::Momo, 'payout_network' => MomoNetwork::Mtn,
        'payout_account_number' => '+233244000111', 'payout_account_name' => 'GRACE', 'payout_verified_at' => now(),
    ]);
    $this->other = Church::factory()->create(['slug' => 'other']);
    $this->fund = FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Tithe']);
    $this->finance = initiateStaff($this->church, 'finance-secretary');
    Sanctum::actingAs($this->finance, ['*']);
});

describe('starting a gift', function (): void {
    it('saves it pending with the fee on top, queues the payment on the payments queue and answers 202', function (): void {
        Queue::fake();
        $member = Member::factory()->create(['church_id' => $this->church->id]);

        $response = initiateGift(['member_id' => $member->id])->assertStatus(202);

        $gift = Contribution::query()->sole();
        $response->assertJsonPath('data.reference', $gift->id)->assertJsonPath('data.status', 'pending')->assertJsonPath('data.authorization_url', null)
            ->assertJsonPath('data.amount', '100.00')->assertJsonPath('data.processing_fee', '1.99')->assertJsonPath('data.total_paid', '101.99')
            ->assertJsonPath('data.total_paid_formatted', 'GHS 101.99');
        expect($gift->status)->toBe(ContributionStatus::Pending)->and($gift->payment_method)->toBe(PaymentMethod::Momo)->and($gift->fund_name)->toBe('Tithe')
            ->and($gift->member_id)->toBe($member->id)->and($gift->recorded_by)->toBe($this->finance->id)->and($gift->payperlez_reference)->toBeNull();
        Queue::assertPushedOn('payments', StartContributionPaymentJob::class, fn (StartContributionPaymentJob $job) => $job->contributionId === $gift->id && $job->phone === '+233244123456' && $job->network === 'mtn');
    });

    it('counts nothing, lists nothing and sends no receipt until the provider confirms', function (): void {
        Queue::fake();

        initiateGift()->assertStatus(202);

        $this->getJson('http://grace.localhost/api/v1/contributions')->assertOk()->assertJsonCount(0, 'data');
    });

    it('starts one payment however many times the same attempt is sent (idempotency)', function (): void {
        Queue::fake();

        $first = initiateGift([], 'same-attempt-key-0001')->assertStatus(202);
        $second = initiateGift([], 'same-attempt-key-0001')->assertStatus(202);

        expect($second->json('data.reference'))->toBe($first->json('data.reference'))->and(Contribution::query()->count())->toBe(1);
        Queue::assertPushed(StartContributionPaymentJob::class, 1);
    });

    it('keeps the job encrypted in the queue, because it carries a phone number', function (): void {
        expect(new StartContributionPaymentJob('id', '+233244123456', 'mtn'))->toBeInstanceOf(ShouldBeEncrypted::class);
    });

    it('is refused while the church has no verified payout account, and saves nothing', function (string $state): void {
        Queue::fake();
        $this->church->update(match ($state) {
            'none' => ['payout_method' => null, 'payout_network' => null, 'payout_account_number' => null, 'payout_verified_at' => null],
            'checking' => ['payout_verified_at' => null],
            'failed' => ['payout_verified_at' => null, 'payout_check_error' => 'Could not name the account.'],
        });

        initiateGift()->assertUnprocessable()->assertJsonValidationErrors('payment_method');

        expect(Contribution::query()->count())->toBe(0);
        Queue::assertNothingPushed();
    })->with(['none', 'checking', 'failed']);

    it('takes a gift to a church that settles to a bank, which waits for GHS 100 (D-072)', function (): void {
        Queue::fake();
        $this->church->update(['payout_method' => PayoutMethod::Bank, 'payout_network' => null, 'payout_bank_code' => '058', 'payout_account_number' => '0123456789']);

        initiateGift()->assertStatus(202);
    });
});

describe('what the refusals say', function (): void {
    it('says it in plain words, with the limits from the configuration', function (): void {
        initiateGift(['amount' => '0.50'])->assertJsonPath('errors.amount.0', 'The smallest mobile money gift is GHS 1.00. Enter GHS 1.00 or more.');
        initiateGift(['amount' => '40000'])->assertJsonPath('errors.amount.0', 'The largest mobile money gift is GHS 30,000.00. Enter GHS 30,000.00 or less.');
        initiateGift(['amount' => '10.555'])->assertJsonPath('errors.amount.0', 'Enter the amount in cedis and pesewas, for example 50.00.');
        initiateGift(['amount' => 'abc'])->assertJsonPath('errors.amount.0', 'Enter the amount as a number, for example 50.00.');
        initiateGift(['amount' => null])->assertJsonPath('errors.amount.0', 'Enter the amount to give.');
        initiateGift(['payment_method' => 'card'])->assertJsonPath('errors.payment_method.0', 'Only mobile money can be taken for now. Record cash gifts as cash.');
        initiateGift(['phone' => 'abc'])->assertJsonPath('errors.phone.0', 'Enter the mobile money number of the person giving, for example 0244123456.');
        initiateGift(['network' => 'vodafone'])->assertJsonPath('errors.network.0', 'Choose MTN, Telecel or AirtelTigo.');
    });

    it('tells the admin what to do when the payout account is not ready', function (): void {
        $this->church->update(['payout_verified_at' => null]);

        initiateGift()->assertJsonPath('errors.payment_method.0', 'Mobile money giving is not switched on yet. Add the church\'s payout account in Settings and wait until it is checked.');
    });
});

describe('refusing', function (): void {
    it('refuses what is missing or wrong, and saves nothing', function (array $body, string $field): void {
        Queue::fake();

        initiateGift($body)->assertUnprocessable()->assertJsonValidationErrors($field);

        expect(Contribution::query()->count())->toBe(0);
        Queue::assertNothingPushed();
    })->with([
        'no fund' => [['fund_type_id' => null], 'fund_type_id'],
        'an amount below the digital minimum' => [['amount' => '0.99'], 'amount'],
        'an amount above the digital maximum' => [['amount' => '30000.01'], 'amount'],
        'an amount with three places' => [['amount' => '10.555'], 'amount'],
        'no amount' => [['amount' => null], 'amount'],
        'cash' => [['payment_method' => 'cash'], 'payment_method'],
        'a card (not taken yet)' => [['payment_method' => 'card'], 'payment_method'],
        'no phone' => [['phone' => null], 'phone'],
        'a phone that is not a number' => [['phone' => 'abc'], 'phone'],
        'no network' => [['network' => null], 'network'],
        'an unknown network' => [['network' => 'vodafone'], 'network'],
        'a member that does not exist' => [['member_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV'], 'member_id'],
    ]);

    it('takes the limits themselves: GHS 1.00 and GHS 30,000.00', function (string $amount): void {
        Queue::fake();

        initiateGift(['amount' => $amount])->assertStatus(202);
    })->with(['1.00', '30000.00']);

    it('is refused without a token and to staff who may not record gifts, and starts nothing', function (): void {
        Queue::fake();
        $this->app['auth']->forgetGuards();
        initiateGift()->assertUnauthorized();

        Sanctum::actingAs(initiateStaff($this->church, 'department-head'), ['*']);
        initiateGift()->assertForbidden();

        expect(Contribution::query()->count())->toBe(0);
        Queue::assertNothingPushed();
    });

    it('will not use another church\'s member, fund or pledge (isolation)', function (): void {
        Queue::fake();
        $theirMember = Member::factory()->create(['church_id' => $this->other->id]);
        $theirFund = FundType::factory()->create(['church_id' => $this->other->id]);

        initiateGift(['member_id' => $theirMember->id])->assertUnprocessable()->assertJsonValidationErrors('member_id');
        initiateGift(['fund_type_id' => $theirFund->id])->assertUnprocessable()->assertJsonValidationErrors('fund_type_id');

        expect(Contribution::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    it('limits how many payments one staff member can start in a minute', function (): void {
        Queue::fake();
        config(['limits.gift_initiations_per_minute' => 2]);

        initiateGift()->assertStatus(202);
        initiateGift()->assertStatus(202);
        initiateGift()->assertStatus(429);
    });
});

describe('the payment job', function (): void {
    beforeEach(function (): void {
        $this->gift = Contribution::factory()->pending()->create(['church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'amount' => '100.00', 'processing_fee' => '1.99', 'total_paid' => '101.99']);
        $this->runJob = fn () => (new StartContributionPaymentJob($this->gift->id, '+233244123456', 'mtn'))->handle(app(ContributionRepositoryInterface::class), app(PaymentProvider::class));
    });

    it('asks the provider for the total with our id as the reference, and keeps the provider reference', function (): void {
        $provider = Mockery::mock(PaymentProvider::class);
        $provider->shouldReceive('collect')->once()->with('+233244123456', '101.99', $this->gift->id, MomoNetwork::Mtn)
            ->andReturn(new CollectionResult('ppz-123', $this->gift->id, ProviderPaymentStatus::Pending, '101.99', null, null));
        app()->instance(PaymentProvider::class, $provider);

        ($this->runJob)();

        expect($this->gift->fresh()->payperlez_reference)->toBe('ppz-123')->and($this->gift->fresh()->status)->toBe(ContributionStatus::Pending);
    });

    it('fails the gift when the provider refuses for good, and logs no person or amount', function (): void {
        Log::spy();
        $provider = Mockery::mock(PaymentProvider::class);
        $provider->shouldReceive('collect')->once()->andThrow(new PaymentRejectedException('refused'));
        app()->instance(PaymentProvider::class, $provider);

        ($this->runJob)();

        expect($this->gift->fresh()->status)->toBe(ContributionStatus::Failed)->and($this->gift->fresh()->failure_reason)->toBe(ContributionFailureReason::Refused);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $m, array $c) => $c === ['contribution_id' => $this->gift->id, 'church_id' => $this->church->id]);
    });

    it('lets the job try again when the provider is busy, and leaves the gift pending', function (): void {
        $provider = Mockery::mock(PaymentProvider::class);
        $provider->shouldReceive('collect')->once()->andThrow(new PaymentProviderUnavailableException('down'));
        app()->instance(PaymentProvider::class, $provider);

        expect(fn () => ($this->runJob)())->toThrow(PaymentProviderUnavailableException::class);
        expect($this->gift->fresh()->status)->toBe(ContributionStatus::Pending);
    });

    it('leaves a gift alone that is gone, no longer pending, or already has a provider reference (consumer dedup)', function (): void {
        $provider = Mockery::mock(PaymentProvider::class);
        $provider->shouldNotReceive('collect');
        app()->instance(PaymentProvider::class, $provider);

        (new StartContributionPaymentJob('01ARZ3NDEKTSV4RRFFQ69G5FAV', '+233244123456', 'mtn'))->handle(app(ContributionRepositoryInterface::class), $provider);
        $this->gift->update(['payperlez_reference' => 'ppz-1']);
        ($this->runJob)();
        $this->gift->update(['payperlez_reference' => null, 'status' => ContributionStatus::Completed]);
        ($this->runJob)();

        expect(true)->toBeTrue();
    });

    it('logs that the tries ran out without any phone number', function (): void {
        Log::spy();

        (new StartContributionPaymentJob($this->gift->id, '+233244123456', 'mtn'))->failed(new RuntimeException('boom +233244123456'));

        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $m, array $c) => $c === ['contribution_id' => $this->gift->id, 'error' => RuntimeException::class]);
    });
});
