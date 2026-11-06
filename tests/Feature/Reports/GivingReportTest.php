<?php

declare(strict_types=1);

use App\Models\Church;
use App\Models\Contribution;
use App\Models\FundType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

// Giving trends (docs/11 story 5.6, docs/08 Reports, docs/02, QA-11 TC-11-04 to 07, D-046, QA-10): what the church received
// each month, by fund, against the months before. Totals only: nothing here names a member. Today is Wednesday 21 October 2026.

const GIVING_REPORT = 'http://grace.localhost/api/v1/reports/giving';

/** A staff member of the church with one role. */
function givingTrendStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

/** A gift to a fund, on a date and time (UTC), not voided unless asked. */
function trendGift(Church $church, FundType $fund, string $amount, string $at, array $attributes = []): Contribution
{
    return Contribution::factory()->create([
        'church_id' => $church->id, 'fund_type_id' => $fund->id, 'fund_name' => $fund->name, 'amount' => $amount, 'contributed_at' => $at, ...$attributes,
    ]);
}

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-21 10:00:00');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace', 'timezone' => 'Africa/Accra']);
    $this->other = Church::factory()->create(['slug' => 'other']);
    $this->tithe = FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Tithe', 'sort_order' => 1]);
    $this->building = FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Building Fund', 'sort_order' => 2]);
    Sanctum::actingAs(givingTrendStaff($this->church, 'church-admin'), ['*']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('the months', function (): void {
    it('gives twelve months, newest first, and a month with nothing given is 0.00, not left out', function (): void {
        trendGift($this->church, $this->tithe, '150.00', '2026-10-05 09:00:00');
        trendGift($this->church, $this->tithe, '100.00', '2026-08-15 09:00:00');

        $rows = $this->getJson(GIVING_REPORT)->assertOk()->json('data');

        expect($rows)->toHaveCount(12)
            ->and($rows[0])->toMatchArray(['month' => '2026-10', 'month_start' => '2026-10-01', 'total' => '150.00', 'total_formatted' => 'GHS 150.00'])
            ->and($rows[1])->toMatchArray(['month' => '2026-09', 'total' => '0.00', 'total_formatted' => 'GHS 0.00', 'by_fund' => []])
            ->and($rows[2])->toMatchArray(['month' => '2026-08', 'total' => '100.00'])
            ->and($rows[11])->toMatchArray(['month' => '2025-11', 'month_start' => '2025-11-01']);
    });

    it('takes the number of months from the request', function (): void {
        expect($this->getJson(GIVING_REPORT.'?months=3')->assertOk()->json('data'))->toHaveCount(3)
            ->and($this->getJson(GIVING_REPORT.'?months=1')->assertOk()->json('data.0.month'))->toBe('2026-10');
    });

    it('splits a month by fund, in the funds\' display order, with each amount in cedis and formatted', function (): void {
        trendGift($this->church, $this->building, '1200.50', '2026-10-05 09:00:00');
        trendGift($this->church, $this->tithe, '300.00', '2026-10-06 09:00:00');
        trendGift($this->church, $this->tithe, '50.25', '2026-10-20 09:00:00');

        $month = $this->getJson(GIVING_REPORT.'?months=1')->assertOk()->json('data.0');

        expect($month['total'])->toBe('1550.75')->and($month['total_formatted'])->toBe('GHS 1,550.75')
            ->and($month['by_fund'])->toBe([
                ['fund_type_id' => $this->tithe->id, 'name' => 'Tithe', 'total' => '350.25', 'total_formatted' => 'GHS 350.25'],
                ['fund_type_id' => $this->building->id, 'name' => 'Building Fund', 'total' => '1200.50', 'total_formatted' => 'GHS 1,200.50'],
            ]);
    });

    it('leaves out gifts that were voided', function (): void {
        trendGift($this->church, $this->tithe, '100.00', '2026-10-05 09:00:00');
        trendGift($this->church, $this->tithe, '999.00', '2026-10-06 09:00:00', ['is_voided' => true, 'voided_at' => now(), 'void_reason' => 'Entered in error', 'voided_by' => User::factory()->create(['church_id' => $this->church->id])->id]);

        expect($this->getJson(GIVING_REPORT.'?months=1')->assertOk()->json('data.0.total'))->toBe('100.00');
    });

    it('adds up to the cent, exactly', function (): void {
        foreach (['0.10', '0.20', '0.30'] as $amount) {
            trendGift($this->church, $this->tithe, $amount, '2026-10-05 09:00:00');
        }

        expect($this->getJson(GIVING_REPORT.'?months=1')->assertOk()->json('data.0.total'))->toBe('0.60');
    });

    it('counts a gift in the month it was given in the church\'s own time zone', function (): void {
        // 23:30 UTC on 30 September is already 1 October in a church 2 hours ahead.
        $this->church->update(['timezone' => 'Europe/Athens']);
        trendGift($this->church, $this->tithe, '70.00', '2026-09-30 23:30:00');

        $rows = $this->getJson(GIVING_REPORT.'?months=2')->assertOk()->json('data');

        expect($rows[0]['total'])->toBe('70.00')->and($rows[1]['total'])->toBe('0.00');
    });

    it('keeps each church to its own gifts (isolation)', function (): void {
        trendGift($this->church, $this->tithe, '10.00', '2026-10-05 09:00:00');
        $theirs = FundType::factory()->create(['church_id' => $this->other->id, 'name' => 'Theirs']);
        trendGift($this->other, $theirs, '5000.00', '2026-10-05 09:00:00');

        $month = $this->getJson(GIVING_REPORT.'?months=1')->assertOk()->json('data.0');

        expect($month['total'])->toBe('10.00')->and(json_encode($month))->not->toContain('Theirs');
    });

    it('names no member and no individual gift', function (): void {
        trendGift($this->church, $this->tithe, '10.00', '2026-10-05 09:00:00');

        $body = $this->getJson(GIVING_REPORT.'?months=1')->assertOk()->getContent();

        expect($body)->not->toContain('member')->not->toContain('contributed_at')->not->toContain('reference');
    });
});

describe('one fund', function (): void {
    it('looks at one fund only', function (): void {
        trendGift($this->church, $this->tithe, '100.00', '2026-10-05 09:00:00');
        trendGift($this->church, $this->building, '900.00', '2026-10-05 09:00:00');

        $month = $this->getJson(GIVING_REPORT.'?months=1&fund_type_id='.$this->tithe->id)->assertOk()->json('data.0');

        expect($month['total'])->toBe('100.00')->and(collect($month['by_fund'])->pluck('name')->all())->toBe(['Tithe']);
    });

    it('gives zeros for a fund that has no gifts', function (): void {
        trendGift($this->church, $this->tithe, '100.00', '2026-10-05 09:00:00');

        $month = $this->getJson(GIVING_REPORT.'?months=1&fund_type_id='.$this->building->id)->assertOk()->json('data.0');

        expect($month['total'])->toBe('0.00')->and($month['by_fund'])->toBe([]);
    });
});

describe('the summary', function (): void {
    it('gives the total of the months asked for, the same number of months before them, and the change', function (): void {
        trendGift($this->church, $this->tithe, '150.00', '2026-10-05 09:00:00');
        trendGift($this->church, $this->tithe, '50.00', '2026-09-05 09:00:00');
        trendGift($this->church, $this->tithe, '100.00', '2026-08-05 09:00:00'); // before the 2 months asked for

        // JSON writes 100.0 as 100, so the change is compared as a number, not as a float.
        expect($this->getJson(GIVING_REPORT.'?months=2')->assertOk()->json('summary'))->toEqual([
            'total' => '200.00', 'total_formatted' => 'GHS 200.00',
            'previous_total' => '100.00', 'previous_total_formatted' => 'GHS 100.00',
            'change_percent' => 100.0,
        ]);
    });

    it('counts a gift made at the very start of the period once, not in the months before as well', function (): void {
        trendGift($this->church, $this->tithe, '80.00', '2026-10-01 00:00:00');

        $summary = $this->getJson(GIVING_REPORT.'?months=1')->assertOk()->json('summary');

        expect($summary['total'])->toBe('80.00')->and($summary['previous_total'])->toBe('0.00');
    });

    it('shows a fall as a negative change, to one decimal place', function (): void {
        trendGift($this->church, $this->tithe, '100.00', '2026-10-05 09:00:00');
        trendGift($this->church, $this->tithe, '300.00', '2026-09-05 09:00:00');

        expect($this->getJson(GIVING_REPORT.'?months=1')->assertOk()->json('summary.change_percent'))->toBe(-66.7);
    });

    it('has no change when nothing was given in the months before', function (): void {
        trendGift($this->church, $this->tithe, '100.00', '2026-10-05 09:00:00');

        $summary = $this->getJson(GIVING_REPORT.'?months=1')->assertOk()->json('summary');

        expect($summary['previous_total'])->toBe('0.00')->and($summary['change_percent'])->toBeNull();
    });

    it('follows the fund filter', function (): void {
        trendGift($this->church, $this->tithe, '100.00', '2026-10-05 09:00:00');
        trendGift($this->church, $this->building, '900.00', '2026-10-05 09:00:00');

        expect($this->getJson(GIVING_REPORT.'?months=1&fund_type_id='.$this->tithe->id)->assertOk()->json('summary.total'))->toBe('100.00');
    });
});

describe('the request', function (): void {
    it('refuses months outside 1 to 24 and a fund that is not an id', function (): void {
        $this->getJson(GIVING_REPORT.'?months=0')->assertStatus(422)->assertJsonValidationErrors('months');
        $this->getJson(GIVING_REPORT.'?months=25')->assertStatus(422)->assertJsonValidationErrors('months');
        $this->getJson(GIVING_REPORT.'?months=lots')->assertStatus(422)->assertJsonValidationErrors('months');
        $this->getJson(GIVING_REPORT.'?fund_type_id=tithe')->assertStatus(422)->assertJsonValidationErrors('fund_type_id');
    });
});

describe('who may read it', function (): void {
    it('refuses a visitor with no sign-in', function (): void {
        $this->app['auth']->forgetGuards();

        $this->getJson(GIVING_REPORT)->assertUnauthorized();
    });

    it('lets the roles with giving.summary read it, the pastor included', function (string $role): void {
        Sanctum::actingAs(givingTrendStaff($this->church, $role), ['*']);

        $this->getJson(GIVING_REPORT)->assertOk();
    })->with(['church-admin', 'pastor', 'finance-secretary']);

    it('refuses the roles without giving.summary', function (string $role): void {
        Sanctum::actingAs(givingTrendStaff($this->church, $role), ['*']);

        $this->getJson(GIVING_REPORT)->assertForbidden()->assertJsonPath('message', 'This action is unauthorized.');
    })->with(['department-head', 'youth-pastor']);
});
