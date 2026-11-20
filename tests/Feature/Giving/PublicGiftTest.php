<?php

declare(strict_types=1);

use App\Enums\ContributionStatus;
use App\Enums\MomoNetwork;
use App\Enums\PaymentMethod;
use App\Enums\PayoutMethod;
use App\Jobs\StartContributionPaymentJob;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\FundType;
use App\Models\Member;
use App\Models\User;
use App\Services\PhoneFingerprint;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

// Anyone can give from the church's public page with no staff involved (D-073, docs/08 The Public Giving Page, step g2): the gift
// is saved pending with the fee on top, linked to no staff member, and the payment is queued. A repeated attempt is reused, a bot
// is turned away quietly, and the page is protected by a rate per phone and per address. The provider is not called.

const GIVE = 'http://grace.localhost/api/v1/give';

/** Starts a public gift with a fresh Idempotency-Key unless one is given. */
function startPublicGift(array $body = [], ?string $key = null, string $url = GIVE): TestResponse
{
    return test()->postJson($url, [
        'fund_type_id' => test()->fund->id, 'amount' => '50.00', 'payment_method' => 'momo', 'phone' => '0244123456', 'network' => 'mtn', ...$body,
    ], ['Idempotency-Key' => $key ?? 'give-'.Str::random(20)]);
}

beforeEach(function (): void {
    Cache::flush();
    Queue::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create([
        'slug' => 'grace', 'payout_method' => PayoutMethod::Momo, 'payout_network' => MomoNetwork::Mtn,
        'payout_account_number' => '+233244000111', 'payout_account_name' => 'GRACE', 'payout_verified_at' => now(),
    ]);
    $this->other = Church::factory()->create(['slug' => 'other']);
    $this->fund = FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Tithe']);
});

describe('starting a gift', function (): void {
    it('needs no sign-in, saves it pending with the fee on top and no staff member, and queues the payment', function (): void {
        $response = startPublicGift(['giver_name' => 'Ama Mensah'])->assertStatus(202);

        $gift = Contribution::query()->sole();
        $response->assertJsonPath('data.reference', $gift->id)->assertJsonPath('data.status', 'pending')->assertJsonPath('data.already_waiting', false)
            ->assertJsonPath('data.amount', '50.00')->assertJsonPath('data.processing_fee', '1.00')->assertJsonPath('data.total_paid', '51.00')
            ->assertJsonPath('data.total_paid_formatted', 'GHS 51.00');
        expect($gift->status)->toBe(ContributionStatus::Pending)->and($gift->payment_method)->toBe(PaymentMethod::Momo)->and($gift->fund_name)->toBe('Tithe')
            ->and($gift->church_id)->toBe($this->church->id)->and($gift->recorded_by)->toBeNull()->and($gift->member_id)->toBeNull()->and($gift->giver_name)->toBe('Ama Mensah');
        Queue::assertPushedOn('payments', StartContributionPaymentJob::class, fn (StartContributionPaymentJob $job) => $job->contributionId === $gift->id && $job->phone === '+233244123456' && $job->network === 'mtn');
    });

    it('keeps the phone only as a keyed hash, and the typed name encrypted', function (): void {
        startPublicGift(['giver_name' => 'Ama Mensah'])->assertStatus(202);

        $row = (array) DB::table('contributions')->first();
        expect($row['payer_phone_hash'])->toBe(app(PhoneFingerprint::class)->of('+233244123456'))
            ->and(json_encode($row))->not->toContain('244123456')->not->toContain('Ama Mensah');
    });

    it('counts and lists nothing, and sends no receipt, until the provider confirms', function (): void {
        startPublicGift()->assertStatus(202);
        Sanctum::actingAs(User::factory()->for($this->church)->create()->assignRole('finance-secretary'), ['*']);

        $this->getJson('http://grace.localhost/api/v1/contributions')->assertOk()->assertJsonCount(0, 'data');
    });

    it('starts one payment however many times the same attempt is sent (idempotency)', function (): void {
        $first = startPublicGift([], 'same-attempt-key-0001')->assertStatus(202);
        $second = startPublicGift([], 'same-attempt-key-0001')->assertStatus(202);

        expect($second->json('data.reference'))->toBe($first->json('data.reference'))->and(Contribution::query()->count())->toBe(1);
        Queue::assertPushed(StartContributionPaymentJob::class, 1);
    });

    it('needs an Idempotency-Key', function (): void {
        $this->postJson(GIVE, ['fund_type_id' => $this->fund->id, 'amount' => '50.00', 'payment_method' => 'momo', 'phone' => '0244123456', 'network' => 'mtn'])->assertStatus(400);

        expect(Contribution::query()->count())->toBe(0);
    });

    it('is turned away, in plain words and saving nothing, while the church cannot be paid', function (string $state): void {
        $this->church->update(match ($state) {
            'none' => ['payout_method' => null, 'payout_network' => null, 'payout_account_number' => null, 'payout_verified_at' => null],
            'checking' => ['payout_verified_at' => null],
            'failed' => ['payout_verified_at' => null, 'payout_check_error' => 'Could not name the account.'],
        });

        startPublicGift()->assertUnprocessable()->assertJsonPath('errors.payment_method.0', 'Giving is not available here yet.');

        expect(Contribution::query()->count())->toBe(0);
        Queue::assertNothingPushed();
    })->with(['none', 'checking', 'failed']);
});

describe('a repeated attempt', function (): void {
    it('gets the payment already waiting, and starts nothing new', function (): void {
        $first = startPublicGift()->assertStatus(202);
        $this->travel(61)->seconds();

        $second = startPublicGift()->assertStatus(202);

        $second->assertJsonPath('data.reference', $first->json('data.reference'))->assertJsonPath('data.already_waiting', true);
        expect(Contribution::query()->count())->toBe(1);
        Queue::assertPushed(StartContributionPaymentJob::class, 1);
    });

    it('is a new gift when the amount, the fund, or the phone is different', function (array $change): void {
        $other = FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Building Fund']);
        startPublicGift()->assertStatus(202);
        $this->travel(61)->seconds();

        $second = startPublicGift(array_map(fn ($v) => $v === 'OTHER_FUND' ? $other->id : $v, $change))->assertStatus(202);

        $second->assertJsonPath('data.already_waiting', false);
        expect(Contribution::query()->count())->toBe(2);
    })->with([
        'another amount' => [['amount' => '60.00']],
        'another fund' => [['fund_type_id' => 'OTHER_FUND']],
        'another phone' => [['phone' => '0200000222']],
    ]);

    it('is a new gift once the waiting one is older than the reuse window, or is no longer waiting', function (): void {
        startPublicGift()->assertStatus(202);
        $this->travel(6)->minutes();
        startPublicGift()->assertStatus(202)->assertJsonPath('data.already_waiting', false);
        expect(Contribution::query()->count())->toBe(2);

        Contribution::query()->update(['status' => ContributionStatus::Failed]);
        $this->travel(61)->seconds();
        startPublicGift()->assertStatus(202)->assertJsonPath('data.already_waiting', false);
        expect(Contribution::query()->count())->toBe(3);
    });

    it('never returns another church\'s waiting payment (isolation)', function (): void {
        $theirs = FundType::factory()->create(['church_id' => $this->other->id]);
        Contribution::factory()->pending()->create([
            'church_id' => $this->other->id, 'fund_type_id' => $theirs->id, 'amount' => '50.00', 'recorded_by' => null,
            'payer_phone_hash' => app(PhoneFingerprint::class)->of('+233244123456'),
        ]);

        startPublicGift()->assertStatus(202)->assertJsonPath('data.already_waiting', false);

        expect(Contribution::query()->withoutGlobalScopes()->count())->toBe(2);
    });
});

describe('protecting the page', function (): void {
    it('lets one phone be asked once a minute, and again a minute later', function (): void {
        startPublicGift()->assertStatus(202);

        startPublicGift()->assertStatus(429)->assertJsonPath('message', 'A payment prompt was just sent to this number. Please wait a minute before asking for another.');
        $this->travel(61)->seconds();
        startPublicGift()->assertStatus(202);
    });

    it('counts the same phone written two ways as one', function (): void {
        startPublicGift(['phone' => '0244123456'])->assertStatus(202);

        startPublicGift(['phone' => '+233244123456'])->assertStatus(429);
    });

    it('does not hold one phone against another', function (): void {
        startPublicGift(['phone' => '0244123456'])->assertStatus(202);

        startPublicGift(['phone' => '0200000222'])->assertStatus(202);
    });

    it('does not let one church\'s wait hold another church\'s giver', function (): void {
        $this->other->update(['payout_method' => PayoutMethod::Momo, 'payout_network' => MomoNetwork::Mtn, 'payout_account_number' => '+233244000999', 'payout_verified_at' => now()]);
        $theirs = FundType::factory()->create(['church_id' => $this->other->id]);
        startPublicGift()->assertStatus(202);

        startPublicGift(['fund_type_id' => $theirs->id], null, 'http://other.localhost/api/v1/give')->assertStatus(202);
    });

    it('limits how many gifts one internet address may start in an hour', function (): void {
        config(['limits.public_give_address_per_hour' => 2]);

        startPublicGift(['phone' => '0200000001'])->assertStatus(202);
        startPublicGift(['phone' => '0200000002'])->assertStatus(202);
        startPublicGift(['phone' => '0200000003'])->assertStatus(429)->assertJsonPath('message', 'Too many gifts are being started from here right now. Please wait a few minutes and try again.');
    });

    it('turns a bot away quietly: the same answer, nothing saved, nothing queued', function (): void {
        $response = startPublicGift(['website' => 'http://spam.example'])->assertStatus(202);

        $response->assertJsonPath('data.status', 'pending')->assertJsonPath('data.already_waiting', false)->assertJsonPath('data.total_paid', '51.00');
        expect(Str::isUlid($response->json('data.reference')))->toBeTrue()->and(Contribution::query()->count())->toBe(0);
        Queue::assertNothingPushed();
    });

    it('does not say whether the phone belongs to a member', function (): void {
        Member::factory()->create(['church_id' => $this->church->id, 'phone' => '+233244123456', 'first_name' => 'Kwame']);

        $body = startPublicGift()->assertStatus(202)->getContent();

        expect($body)->not->toContain('Kwame')->not->toContain('member');
    });
});

describe('what is refused', function (): void {
    it('says what is wrong, in plain words, and saves nothing', function (array $body, string $field): void {
        startPublicGift($body)->assertUnprocessable()->assertJsonValidationErrors($field);

        expect(Contribution::query()->count())->toBe(0);
        Queue::assertNothingPushed();
    })->with([
        'no fund' => [['fund_type_id' => null], 'fund_type_id'],
        'an amount below the minimum' => [['amount' => '0.99'], 'amount'],
        'an amount above the maximum' => [['amount' => '30000.01'], 'amount'],
        'not a number' => [['amount' => 'abc'], 'amount'],
        'cash' => [['payment_method' => 'cash'], 'payment_method'],
        'a card' => [['payment_method' => 'card'], 'payment_method'],
        'no phone' => [['phone' => null], 'phone'],
        'a bad phone' => [['phone' => 'abc'], 'phone'],
        'an unknown network' => [['network' => 'vodafone'], 'network'],
        'a name that is too long' => [['giver_name' => str_repeat('a', 101)], 'giver_name'],
    ]);

    it('uses the same plain sentences as the staff form', function (): void {
        startPublicGift(['amount' => '0.50'])->assertJsonPath('errors.amount.0', 'The smallest mobile money gift is GHS 1.00. Enter GHS 1.00 or more.');
        $this->travel(61)->seconds();
        startPublicGift(['fund_type_id' => null])->assertJsonPath('errors.fund_type_id.0', 'Choose what you are giving to.');
    });

    it('will not take another church\'s fund, and the church cannot be chosen by the body (isolation)', function (): void {
        $theirs = FundType::factory()->create(['church_id' => $this->other->id]);

        startPublicGift(['fund_type_id' => $theirs->id])->assertUnprocessable()->assertJsonPath('errors.fund_type_id.0', 'That is not available to give to. Choose again.');
        $this->travel(61)->seconds();
        startPublicGift(['church_id' => $this->other->id, 'slug' => 'other'])->assertStatus(202);

        expect(Contribution::query()->withoutGlobalScopes()->sole()->church_id)->toBe($this->church->id);
    });

    it('will not take a fund that is switched off', function (): void {
        $this->fund->update(['is_active' => false]);

        startPublicGift()->assertUnprocessable()->assertJsonValidationErrors('fund_type_id');
    });

    it('is a 404 on an address that belongs to no church', function (): void {
        startPublicGift([], null, 'http://nobody.localhost/api/v1/give')->assertNotFound();
    });
});

describe('a completed public gift in the staff screens', function (): void {
    it('shows with no staff name, and the export says it came from the giving page', function (): void {
        $gift = Contribution::factory()->create([
            'church_id' => $this->church->id, 'fund_type_id' => $this->fund->id, 'recorded_by' => null, 'payment_method' => PaymentMethod::Momo, 'giver_name' => 'Ama Mensah',
        ]);
        Sanctum::actingAs(User::factory()->for($this->church)->create()->assignRole('finance-secretary'), ['*']);

        $this->getJson('http://grace.localhost/api/v1/contributions')->assertOk()->assertJsonPath('data.0.recorded_by', null);
        $this->getJson('http://grace.localhost/api/v1/contributions/'.$gift->id)->assertOk()->assertJsonPath('data.recorded_by', null);
    });
});
