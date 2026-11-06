<?php

declare(strict_types=1);

use App\Models\AttendanceRecord;
use App\Models\Church;
use App\Models\Member;
use App\Models\Service;
use App\Models\User;
use App\Models\VisitorRecord;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

// Attendance trends (docs/11 story 5.5, docs/08 Reports, QA-11 TC-11-01 to 03, QA-10): how many people came, by week or by
// service. Today is Wednesday 21 October 2026, so the current week is 2026-W43 (Monday 19 October).

const ATTENDANCE_REPORT = 'http://grace.localhost/api/v1/reports/attendance';

/** A staff member of the church with one role. */
function trendsStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

/** A service on a date, with this many members checked in. */
function trendService(Church $church, string $date, int $present, array $attributes = []): Service
{
    $service = Service::factory()->create(['church_id' => $church->id, 'service_date' => $date, ...$attributes]);
    $members = Member::factory()->count($present)->create(['church_id' => $church->id]);
    foreach ($members as $member) {
        AttendanceRecord::factory()->create(['church_id' => $church->id, 'service_id' => $service->id, 'member_id' => $member->id]);
    }

    return $service;
}

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-21 10:00:00');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace', 'timezone' => 'Africa/Accra']);
    $this->other = Church::factory()->create(['slug' => 'other']);
    Sanctum::actingAs(trendsStaff($this->church, 'church-admin'), ['*']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('by week (the default)', function (): void {
    it('gives twelve weeks, newest first, and a week with no service is 0, not left out', function (): void {
        trendService($this->church, '2026-10-18', 5);
        trendService($this->church, '2026-10-04', 3);

        $rows = $this->getJson(ATTENDANCE_REPORT)->assertOk()->assertJsonPath('group_by', 'week')->json('data');

        expect($rows)->toHaveCount(12)
            ->and($rows[0])->toMatchArray(['week' => '2026-W43', 'week_start' => '2026-10-19', 'total' => 0, 'first_timers' => 0])
            // Sunday 18 October ends the week that began on Monday 12 October; Sunday 4 October ends the week of 28 September.
            ->and($rows[1])->toMatchArray(['week' => '2026-W42', 'week_start' => '2026-10-12', 'total' => 5, 'services' => 1])
            ->and($rows[2])->toMatchArray(['week' => '2026-W41', 'week_start' => '2026-10-05', 'total' => 0, 'services' => 0])
            ->and($rows[3])->toMatchArray(['week' => '2026-W40', 'week_start' => '2026-09-28', 'total' => 3, 'services' => 1])
            ->and($rows[11])->toMatchArray(['week' => '2026-W32', 'week_start' => '2026-08-03', 'total' => 0]);
    });

    it('takes the number of weeks from the request', function (): void {
        expect($this->getJson(ATTENDANCE_REPORT.'?weeks=4')->assertOk()->json('data'))->toHaveCount(4)
            ->and($this->getJson(ATTENDANCE_REPORT.'?weeks=1')->assertOk()->json('data.0.week'))->toBe('2026-W43');
    });

    it('adds up the services of a week, members and visitors together', function (): void {
        $first = trendService($this->church, '2026-10-19', 4);
        trendService($this->church, '2026-10-21', 2, ['name' => 'Midweek Prayer']);
        $visitor = VisitorRecord::factory()->create(['church_id' => $this->church->id, 'first_visit_date' => '2026-10-19']);
        AttendanceRecord::factory()->create(['church_id' => $this->church->id, 'service_id' => $first->id, 'member_id' => null, 'visitor_record_id' => $visitor->id]);

        expect($this->getJson(ATTENDANCE_REPORT.'?weeks=2')->assertOk()->json('data.0.total'))->toBe(7);
    });

    it('counts exactly what was recorded, and not a record that was removed', function (): void {
        $service = trendService($this->church, '2026-10-19', 5);
        AttendanceRecord::query()->where('service_id', $service->id)->first()->delete();

        expect($this->getJson(ATTENDANCE_REPORT.'?weeks=1')->assertOk()->json('data.0.total'))->toBe(4);
    });

    it('uses a paper headcount in place of the digital count', function (): void {
        trendService($this->church, '2026-10-19', 5, ['headcount_override' => 120]);
        Service::factory()->create(['church_id' => $this->church->id, 'service_date' => '2026-10-20', 'headcount_override' => 30]);

        expect($this->getJson(ATTENDANCE_REPORT.'?weeks=1')->assertOk()->json('data.0.total'))->toBe(150);
    });

    it('ignores a service nobody recorded, one still to come, and one older than the weeks asked for', function (): void {
        Service::factory()->create(['church_id' => $this->church->id, 'service_date' => '2026-10-19']);
        trendService($this->church, '2026-10-25', 9);
        trendService($this->church, '2026-10-11', 8);

        $response = $this->getJson(ATTENDANCE_REPORT.'?weeks=1')->assertOk();

        expect($response->json('data.0.total'))->toBe(0)->and($response->json('summary'))->toBe(['average' => 0, 'highest' => 0, 'lowest' => 0]);
    });

    it('counts first-time visitors in the week of their first visit', function (): void {
        VisitorRecord::factory()->create(['church_id' => $this->church->id, 'first_visit_date' => '2026-10-20']);
        VisitorRecord::factory()->create(['church_id' => $this->church->id, 'first_visit_date' => '2026-10-19']);
        VisitorRecord::factory()->create(['church_id' => $this->church->id, 'first_visit_date' => '2026-10-18']);
        VisitorRecord::factory()->create(['church_id' => $this->other->id, 'first_visit_date' => '2026-10-20']);
        VisitorRecord::factory()->create(['church_id' => $this->church->id, 'first_visit_date' => '2026-10-20'])->delete();

        $rows = $this->getJson(ATTENDANCE_REPORT.'?weeks=2')->assertOk()->json('data');

        expect($rows[0]['first_timers'])->toBe(2)->and($rows[1]['first_timers'])->toBe(1);
    });

    it('summarises only the weeks in which a service was held: average, highest, lowest', function (): void {
        trendService($this->church, '2026-10-18', 10);
        trendService($this->church, '2026-10-11', 20);
        trendService($this->church, '2026-10-04', 5);

        expect($this->getJson(ATTENDANCE_REPORT.'?weeks=6')->assertOk()->json('summary'))->toBe(['average' => 12, 'highest' => 20, 'lowest' => 5]);
    });

    it('rounds the average to a whole person', function (): void {
        trendService($this->church, '2026-10-18', 1);
        trendService($this->church, '2026-10-11', 2);

        expect($this->getJson(ATTENDANCE_REPORT.'?weeks=3')->assertOk()->json('summary.average'))->toBe(2);
    });

    it('keeps each church to its own services (isolation)', function (): void {
        trendService($this->church, '2026-10-19', 3);
        trendService($this->other, '2026-10-19', 50);

        expect($this->getJson(ATTENDANCE_REPORT.'?weeks=1')->assertOk()->json('data.0.total'))->toBe(3);
    });

    it('works out the weeks in the church\'s own time zone', function (): void {
        // 23:30 on Sunday in UTC is already Monday 00:30 in a church 1 hour ahead.
        CarbonImmutable::setTestNow('2026-10-25 23:30:00');
        $this->church->update(['timezone' => 'Europe/Paris']);
        trendService($this->church, '2026-10-26', 6);

        expect($this->getJson(ATTENDANCE_REPORT.'?weeks=1')->assertOk()->json('data.0'))->toMatchArray(['week' => '2026-W44', 'total' => 6]);
    });
});

describe('by service', function (): void {
    it('gives one row per service, newest first, with the service and its total', function (): void {
        $older = trendService($this->church, '2026-10-04', 3, ['name' => 'Sunday Morning Service']);
        $newer = trendService($this->church, '2026-10-18', 5, ['name' => 'Sunday Evening Service']);

        $response = $this->getJson(ATTENDANCE_REPORT.'?group_by=service')->assertOk()->assertJsonPath('group_by', 'service');

        expect($response->json('data'))->toBe([
            ['service_id' => $newer->id, 'service_date' => '2026-10-18', 'service_name' => 'Sunday Evening Service', 'total' => 5],
            ['service_id' => $older->id, 'service_date' => '2026-10-04', 'service_name' => 'Sunday Morning Service', 'total' => 3],
        ])->and($response->json('summary'))->toBe(['average' => 4, 'highest' => 5, 'lowest' => 3]);
    });

    it('lists only services inside the weeks asked for, that were held, and are not still to come', function (): void {
        trendService($this->church, '2026-10-19', 2);
        trendService($this->church, '2026-10-11', 9);
        trendService($this->church, '2026-10-25', 9);
        Service::factory()->create(['church_id' => $this->church->id, 'service_date' => '2026-10-20']);

        expect(collect($this->getJson(ATTENDANCE_REPORT.'?group_by=service&weeks=1')->assertOk()->json('data'))->pluck('total')->all())->toBe([2]);
    });
});

describe('the request', function (): void {
    it('refuses a number of weeks that is not 1 to 52, and a grouping that does not exist', function (): void {
        $this->getJson(ATTENDANCE_REPORT.'?weeks=0')->assertStatus(422)->assertJsonValidationErrors('weeks');
        $this->getJson(ATTENDANCE_REPORT.'?weeks=53')->assertStatus(422)->assertJsonValidationErrors('weeks');
        $this->getJson(ATTENDANCE_REPORT.'?weeks=many')->assertStatus(422)->assertJsonValidationErrors('weeks');
        $this->getJson(ATTENDANCE_REPORT.'?group_by=month')->assertStatus(422)->assertJsonValidationErrors('group_by');
    });
});

describe('who may read it', function (): void {
    it('refuses a visitor with no sign-in', function (): void {
        $this->app['auth']->forgetGuards();

        $this->getJson(ATTENDANCE_REPORT)->assertUnauthorized();
    });

    it('lets the roles with reports.view read it', function (string $role): void {
        Sanctum::actingAs(trendsStaff($this->church, $role), ['*']);

        $this->getJson(ATTENDANCE_REPORT)->assertOk();
    })->with(['church-admin', 'pastor', 'finance-secretary']);

    it('refuses the roles without reports.view', function (string $role): void {
        Sanctum::actingAs(trendsStaff($this->church, $role), ['*']);

        $this->getJson(ATTENDANCE_REPORT)->assertForbidden()->assertJsonPath('message', 'This action is unauthorized.');
    })->with(['department-head', 'youth-pastor']);
});
