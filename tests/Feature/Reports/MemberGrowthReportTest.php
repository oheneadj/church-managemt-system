<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Member;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

// Member growth (docs/11 story 5.7, docs/08 Reports, docs/02, QA-11 TC-11-08, D-055 point 5, QA-10): how many people became
// active members each month, how many left active membership, and how many active members there were at each month's end. It
// is read from the member change history. Today is Wednesday 21 October 2026.

const MEMBER_GROWTH_REPORT = 'http://grace.localhost/api/v1/reports/members';

/** A staff member of the church with one role. */
function growthStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

/** One line of a member's change history: added with a status ($old null), or moved from one status to another. */
function memberHistory(Church $church, ?string $old, string $new, string $at, array $attributes = []): AuditLog
{
    return AuditLog::factory()->create([
        'church_id' => $church->id,
        'auditable_type' => (new Member)->getMorphClass(),
        'auditable_id' => (string) Str::ulid(),
        'event' => $old === null ? AuditEvent::Created : AuditEvent::StatusChanged,
        'changed_fields' => ['status'],
        'old_values' => $old === null ? null : ['status' => $old],
        'new_values' => ['status' => $new],
        'created_at' => $at,
        ...$attributes,
    ]);
}

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-10-21 10:00:00');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace', 'timezone' => 'Africa/Accra']);
    $this->other = Church::factory()->create(['slug' => 'other']);
    Sanctum::actingAs(growthStaff($this->church, 'church-admin'), ['*']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('the months', function (): void {
    it('gives twelve months, newest first, and a quiet month is all zeros, not left out', function (): void {
        memberHistory($this->church, null, 'active', '2026-10-05 09:00:00');

        $rows = $this->getJson(MEMBER_GROWTH_REPORT)->assertOk()->json('data');

        expect($rows)->toHaveCount(12)
            ->and($rows[0])->toMatchArray(['month' => '2026-10', 'month_start' => '2026-10-01', 'new' => 1, 'lost' => 0, 'net' => 1])
            ->and($rows[1])->toMatchArray(['month' => '2026-09', 'new' => 0, 'lost' => 0, 'net' => 0])
            ->and($rows[11])->toMatchArray(['month' => '2025-11', 'month_start' => '2025-11-01']);
    });

    it('takes the number of months from the request', function (): void {
        expect($this->getJson(MEMBER_GROWTH_REPORT.'?months=3')->assertOk()->json('data'))->toHaveCount(3)
            ->and($this->getJson(MEMBER_GROWTH_REPORT.'?months=1')->assertOk()->json('data.0.month'))->toBe('2026-10');
    });
});

describe('who counts as new', function (): void {
    it('counts people who became active: added as active, a prospect confirmed, or a member who came back', function (): void {
        memberHistory($this->church, null, 'active', '2026-10-02 09:00:00');
        memberHistory($this->church, 'prospect', 'active', '2026-10-03 09:00:00');
        memberHistory($this->church, 'inactive', 'active', '2026-10-04 09:00:00');

        expect($this->getJson(MEMBER_GROWTH_REPORT.'?months=1')->assertOk()->json('data.0.new'))->toBe(3);
    });

    it('does not count a prospect, or any other change', function (): void {
        memberHistory($this->church, null, 'prospect', '2026-10-02 09:00:00');
        memberHistory($this->church, 'prospect', 'inactive', '2026-10-03 09:00:00');
        memberHistory($this->church, null, 'active', '2026-10-04 09:00:00', ['event' => AuditEvent::Updated]);

        expect($this->getJson(MEMBER_GROWTH_REPORT.'?months=1')->assertOk()->json('data.0.new'))->toBe(0);
    });
});

describe('who counts as lost', function (): void {
    it('counts active members who moved out of active membership, whichever way they went', function (string $to): void {
        memberHistory($this->church, 'active', $to, '2026-10-05 09:00:00');

        $month = $this->getJson(MEMBER_GROWTH_REPORT.'?months=1')->assertOk()->json('data.0');

        expect($month['lost'])->toBe(1)->and($month['new'])->toBe(0)->and($month['net'])->toBe(-1);
    })->with(['inactive', 'transferred', 'deceased', 'archived', 'prospect']);

    it('does not count a status change that left the member active, which the history does record', function (): void {
        memberHistory($this->church, 'active', 'active', '2026-10-05 09:00:00');

        $month = $this->getJson(MEMBER_GROWTH_REPORT.'?months=1')->assertOk()->json('data.0');

        expect($month['lost'])->toBe(0)->and($month['new'])->toBe(0);
    });

    it('does not count someone who was never active', function (): void {
        memberHistory($this->church, 'prospect', 'archived', '2026-10-05 09:00:00');

        expect($this->getJson(MEMBER_GROWTH_REPORT.'?months=1')->assertOk()->json('data.0.lost'))->toBe(0);
    });
});

describe('the active total', function (): void {
    it('is today\'s count for this month and is worked back from it, month by month', function (): void {
        Member::factory()->count(10)->create(['church_id' => $this->church->id, 'status' => 'active']);
        Member::factory()->count(4)->create(['church_id' => $this->church->id, 'status' => 'inactive']);
        memberHistory($this->church, null, 'active', '2026-10-02 09:00:00');
        memberHistory($this->church, null, 'active', '2026-10-03 09:00:00');
        memberHistory($this->church, 'active', 'inactive', '2026-10-04 09:00:00');
        memberHistory($this->church, null, 'active', '2026-09-10 09:00:00');
        memberHistory($this->church, 'active', 'transferred', '2026-08-10 09:00:00');
        memberHistory($this->church, 'active', 'inactive', '2026-08-11 09:00:00');

        $rows = $this->getJson(MEMBER_GROWTH_REPORT.'?months=4')->assertOk()->json('data');

        // October: +2 -1 = +1, so September ended with 10 - 1 = 9. September: +1, so August ended with 9 - 1 = 8.
        // August: -2, so July ended with 8 + 2 = 10.
        expect(array_column($rows, 'active_total'))->toBe([10, 9, 8, 10]);
    });

    it('ignores another church\'s members', function (): void {
        Member::factory()->count(5)->create(['church_id' => $this->church->id, 'status' => 'active']);
        Member::factory()->count(50)->create(['church_id' => $this->other->id, 'status' => 'active']);

        expect($this->getJson(MEMBER_GROWTH_REPORT.'?months=1')->assertOk()->json('data.0.active_total'))->toBe(5);
    });
});

describe('the summary', function (): void {
    it('adds up the months asked for', function (): void {
        memberHistory($this->church, null, 'active', '2026-10-02 09:00:00');
        memberHistory($this->church, null, 'active', '2026-09-02 09:00:00');
        memberHistory($this->church, 'active', 'inactive', '2026-09-03 09:00:00');
        memberHistory($this->church, null, 'active', '2026-07-02 09:00:00'); // before the 2 months asked for

        expect($this->getJson(MEMBER_GROWTH_REPORT.'?months=2')->assertOk()->json('summary'))->toBe(['new' => 2, 'lost' => 1, 'net' => 1]);
    });
});

describe('what is left out', function (): void {
    it('ignores a history line whose values were erased', function (): void {
        memberHistory($this->church, null, '[deleted]', '2026-10-02 09:00:00');
        memberHistory($this->church, '[deleted]', '[deleted]', '2026-10-03 09:00:00');

        $month = $this->getJson(MEMBER_GROWTH_REPORT.'?months=1')->assertOk()->json('data.0');

        expect($month['new'])->toBe(0)->and($month['lost'])->toBe(0);
    });

    it('ignores the history of anything that is not a member, such as a staff account being switched off', function (): void {
        memberHistory($this->church, 'active', 'inactive', '2026-10-05 09:00:00', ['auditable_type' => (new User)->getMorphClass()]);

        expect($this->getJson(MEMBER_GROWTH_REPORT.'?months=1')->assertOk()->json('data.0.lost'))->toBe(0);
    });

    it('keeps each church to its own history (isolation)', function (): void {
        memberHistory($this->church, null, 'active', '2026-10-05 09:00:00');
        memberHistory($this->other, null, 'active', '2026-10-05 09:00:00');
        memberHistory($this->other, 'active', 'inactive', '2026-10-06 09:00:00');

        $month = $this->getJson(MEMBER_GROWTH_REPORT.'?months=1')->assertOk()->json('data.0');

        expect($month['new'])->toBe(1)->and($month['lost'])->toBe(0);
    });

    it('puts a change in the month it happened in the church\'s own time zone', function (): void {
        // 23:30 UTC on 30 September is already 1 October in a church 2 hours ahead.
        $this->church->update(['timezone' => 'Europe/Athens']);
        memberHistory($this->church, null, 'active', '2026-09-30 23:30:00');

        $rows = $this->getJson(MEMBER_GROWTH_REPORT.'?months=2')->assertOk()->json('data');

        expect($rows[0]['new'])->toBe(1)->and($rows[1]['new'])->toBe(0);
    });
});

describe('from the real changes', function (): void {
    it('follows members added and moved through the API', function (): void {
        $member = $this->postJson('http://grace.localhost/api/v1/members', ['first_name' => 'Kojo', 'last_name' => 'Mensah', 'gender' => 'male', 'phone' => '0244000111', 'status' => 'active'])->assertCreated()->json('data.id');
        $this->postJson('http://grace.localhost/api/v1/members', ['first_name' => 'Ama', 'last_name' => 'Owusu', 'gender' => 'female', 'phone' => '0244000222', 'status' => 'prospect'])->assertCreated();
        $this->patchJson("http://grace.localhost/api/v1/members/{$member}/status", ['status' => 'inactive', 'note' => 'Moved away'])->assertOk();

        $report = $this->getJson(MEMBER_GROWTH_REPORT.'?months=1')->assertOk();

        expect($report->json('data.0'))->toMatchArray(['new' => 1, 'lost' => 1, 'net' => 0, 'active_total' => 0]);
    });
});

describe('the request', function (): void {
    it('refuses months outside 1 to 24', function (): void {
        $this->getJson(MEMBER_GROWTH_REPORT.'?months=0')->assertStatus(422)->assertJsonValidationErrors('months');
        $this->getJson(MEMBER_GROWTH_REPORT.'?months=25')->assertStatus(422)->assertJsonValidationErrors('months');
        $this->getJson(MEMBER_GROWTH_REPORT.'?months=lots')->assertStatus(422)->assertJsonValidationErrors('months');
    });
});

describe('who may read it', function (): void {
    it('refuses a visitor with no sign-in', function (): void {
        $this->app['auth']->forgetGuards();

        $this->getJson(MEMBER_GROWTH_REPORT)->assertUnauthorized();
    });

    it('lets the roles with reports.view read it, the finance secretary included', function (string $role): void {
        Sanctum::actingAs(growthStaff($this->church, $role), ['*']);

        $this->getJson(MEMBER_GROWTH_REPORT)->assertOk();
    })->with(['church-admin', 'pastor', 'finance-secretary']);

    it('refuses the roles without reports.view', function (string $role): void {
        Sanctum::actingAs(growthStaff($this->church, $role), ['*']);

        $this->getJson(MEMBER_GROWTH_REPORT)->assertForbidden()->assertJsonPath('message', 'This action is unauthorized.');
    })->with(['department-head', 'youth-pastor']);
});
