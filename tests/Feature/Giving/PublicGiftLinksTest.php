<?php

declare(strict_types=1);

use App\Enums\MemberStatus;
use App\Enums\MomoNetwork;
use App\Enums\PayoutMethod;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\FundType;
use App\Models\Member;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

// What a gift given on the public page belongs to (D-073 step g3): the service being held when it was given, so the offering report adds
// electronic gifts to the cash taken at that service, and the member whose phone it was paid from. The church's own time decides the day.

const GIVE_LINKS = 'http://grace.localhost/api/v1/give';

/** Starts a public gift from the given phone. */
function giveFrom(string $phone = '0244123456'): TestResponse
{
    return test()->postJson(GIVE_LINKS, [
        'fund_type_id' => test()->fund->id, 'amount' => '50.00', 'payment_method' => 'momo', 'phone' => $phone, 'network' => 'mtn',
    ], ['Idempotency-Key' => 'link-'.Str::random(20)]);
}

/** A service of the test church on a date, with an optional start time and open switch. */
function churchService(array $attributes = []): Service
{
    return Service::factory()->create(['church_id' => test()->church->id, 'service_date' => '2026-11-08', 'starts_at' => null, 'is_open' => false, ...$attributes]);
}

beforeEach(function (): void {
    Cache::flush();
    Queue::fake();
    $this->church = Church::factory()->create([
        'slug' => 'grace', 'timezone' => 'Africa/Accra', 'payout_method' => PayoutMethod::Momo, 'payout_network' => MomoNetwork::Mtn,
        'payout_account_number' => '+233244000111', 'payout_account_name' => 'GRACE', 'payout_verified_at' => now(),
    ]);
    $this->other = Church::factory()->create(['slug' => 'other', 'timezone' => 'Africa/Accra']);
    $this->fund = FundType::factory()->create(['church_id' => $this->church->id]);
    $this->travelTo(CarbonImmutable::parse('2026-11-08 10:30:00', 'UTC'));
});

describe('the service', function (): void {
    it('is the service that started last today, once its start time has passed', function (): void {
        churchService(['name' => 'Early', 'starts_at' => '08:00:00']);
        $main = churchService(['name' => 'Main', 'starts_at' => '10:00:00']);
        churchService(['name' => 'Evening', 'starts_at' => '18:00:00']);

        giveFrom()->assertStatus(202);

        expect(Contribution::query()->sole()->service_id)->toBe($main->id);
    });

    it('is the service open for check-in, even if a later one has started', function (): void {
        $open = churchService(['name' => 'Open', 'starts_at' => '08:00:00', 'is_open' => true, 'checkin_opened_at' => now()->subHours(2)]);
        churchService(['name' => 'Main', 'starts_at' => '10:00:00']);

        giveFrom()->assertStatus(202);

        expect(Contribution::query()->sole()->service_id)->toBe($open->id);
    });

    it('is the service opened first when two are open', function (): void {
        $first = churchService(['name' => 'First', 'is_open' => true, 'checkin_opened_at' => now()->subHours(2)]);
        churchService(['name' => 'Second', 'is_open' => true, 'checkin_opened_at' => now()->subHour()]);

        giveFrom()->assertStatus(202);

        expect(Contribution::query()->sole()->service_id)->toBe($first->id);
    });

    it('is a service with no start time only while it is open', function (): void {
        $untimed = churchService(['name' => 'Untimed']);

        giveFrom()->assertStatus(202);
        expect(Contribution::query()->sole()->service_id)->toBeNull();

        $untimed->update(['is_open' => true, 'checkin_opened_at' => now()]);
        $this->travel(61)->seconds();
        giveFrom('0200000222')->assertStatus(202);
        expect(Contribution::query()->latest('id')->first()->service_id)->toBe($untimed->id);
    });

    it('is no service before the first one of the day has started, or on a day with none', function (): void {
        churchService(['starts_at' => '12:00:00']);
        churchService(['service_date' => '2026-11-09', 'starts_at' => '08:00:00']);
        churchService(['service_date' => '2026-11-01', 'starts_at' => '08:00:00', 'is_open' => true, 'checkin_opened_at' => now()->subWeek()]);

        giveFrom()->assertStatus(202);

        expect(Contribution::query()->sole()->service_id)->toBeNull();
    });

    it('never links another church\'s service (isolation)', function (): void {
        Service::factory()->create(['church_id' => $this->other->id, 'service_date' => '2026-11-08', 'starts_at' => '10:00:00', 'is_open' => true, 'checkin_opened_at' => now()]);

        giveFrom()->assertStatus(202);

        expect(Contribution::query()->sole()->service_id)->toBeNull();
    });

    it('goes by the church\'s own day and time, not the server\'s', function (): void {
        // 23:30 UTC on Sunday the 8th is already 12:30 on Monday the 9th in Auckland (UTC+13), so for that church the service held on
        // the 9th has started at 10:00, though on the server's clock it is still the 8th.
        $this->church->update(['timezone' => 'Pacific/Auckland']);
        $sunday = churchService(['service_date' => '2026-11-09', 'starts_at' => '10:00:00']);
        $this->travelTo(CarbonImmutable::parse('2026-11-08 23:30:00', 'UTC'));

        giveFrom()->assertStatus(202);

        expect(Contribution::query()->sole()->service_id)->toBe($sunday->id);
    });
});

describe('the member', function (): void {
    it('is the living member of this church whose phone it is', function (): void {
        $member = Member::factory()->create(['church_id' => $this->church->id, 'phone' => '+233244123456']);

        giveFrom('0244123456')->assertStatus(202);

        expect(Contribution::query()->sole()->member_id)->toBe($member->id);
    });

    it('is nobody when no member has that phone, or the member has died, or the phone is another church\'s member', function (string $case): void {
        match ($case) {
            'none' => null,
            'deceased' => Member::factory()->create(['church_id' => $this->church->id, 'phone' => '+233244123456', 'status' => MemberStatus::Deceased]),
            'other church' => Member::factory()->create(['church_id' => $this->other->id, 'phone' => '+233244123456']),
        };

        giveFrom('0244123456')->assertStatus(202);

        expect(Contribution::query()->sole()->member_id)->toBeNull();
    })->with(['none', 'deceased', 'other church']);

    it('never shows in the answer whether a phone matched, or who', function (): void {
        $member = Member::factory()->create(['church_id' => $this->church->id, 'phone' => '+233244123456', 'first_name' => 'Kwame']);
        $matched = giveFrom('0244123456')->assertStatus(202);
        $this->travel(61)->seconds();
        $unmatched = giveFrom('0200000222')->assertStatus(202);

        expect(array_keys($matched->json('data')))->toBe(array_keys($unmatched->json('data')))
            ->and($matched->getContent())->not->toContain('Kwame')->not->toContain($member->id);
    });
});
