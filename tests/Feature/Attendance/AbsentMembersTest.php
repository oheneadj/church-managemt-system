<?php

declare(strict_types=1);

use App\Models\AttendanceRecord;
use App\Models\Church;
use App\Models\Member;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

// Members who missed the last services in a row (docs/11 story 3.13, QA-05 TC-05-12), worked out when asked.

const ABSENT = 'http://grace.localhost/api/v1/attendance/absent';

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->admin = User::factory()->for($this->church)->create();
    $this->admin->assignRole('church-admin');
    Sanctum::actingAs($this->admin, ['*']);
    // Four Sundays that were held, oldest first; the members below joined before all of them.
    $this->sundays = collect(['2026-10-04', '2026-10-11', '2026-10-18', '2026-10-25'])->map(function (string $date): Service {
        $service = Service::factory()->create(['church_id' => $this->church->id, 'service_date' => $date, 'type' => 'sunday']);
        AttendanceRecord::factory()->create(['church_id' => $this->church->id, 'service_id' => $service->id]);

        return $service;
    });
    $this->travelTo('2026-10-26 12:00:00');
});

/** A new active member who joined long before the services. */
function oldMember(Church $church, string $name): Member
{
    return Member::factory()->create(['church_id' => $church->id, 'first_name' => $name, 'status' => 'active', 'created_at' => '2026-01-01 00:00:00']);
}

/** Checks the member in to the given services. */
function attended(Church $church, Member $member, iterable $services): void
{
    foreach ($services as $service) {
        AttendanceRecord::factory()->create(['church_id' => $church->id, 'service_id' => $service->id, 'member_id' => $member->id]);
    }
}

it('lists a member who missed the last three Sundays (QA-05 TC-05-12)', function (): void {
    $absent = oldMember($this->church, 'Emmanuel');
    attended($this->church, $absent, [$this->sundays[0]]);

    $names = collect($this->getJson(ABSENT)->assertOk()->json('data'))->pluck('full_name');

    expect($names->contains(fn ($name) => str_contains($name, 'Emmanuel')))->toBeTrue();
});

it('does not list a member who came to any of the last three', function (): void {
    $present = oldMember($this->church, 'Present');
    attended($this->church, $present, [$this->sundays[3]]);

    $names = collect($this->getJson(ABSENT)->assertOk()->json('data'))->pluck('full_name');

    expect($names->contains(fn ($name) => str_contains($name, 'Present')))->toBeFalse();
});

it('shows when the member was last checked in', function (): void {
    $member = oldMember($this->church, 'Emmanuel');
    $service = $this->sundays[0];
    AttendanceRecord::factory()->create(['church_id' => $this->church->id, 'service_id' => $service->id, 'member_id' => $member->id, 'checked_in_at' => '2026-10-04 09:10:00']);

    $row = collect($this->getJson(ABSENT)->json('data'))->firstWhere('id', $member->id);

    expect($row['last_attended_at'])->toBe('2026-10-04T09:10:00+00:00');
});

it('gives null for a member who has never been checked in', function (): void {
    $member = oldMember($this->church, 'Never');

    expect(collect($this->getJson(ABSENT)->json('data'))->firstWhere('id', $member->id)['last_attended_at'])->toBeNull();
});

it('leaves out a member who joined after the oldest of those services', function (): void {
    $new = Member::factory()->create(['church_id' => $this->church->id, 'first_name' => 'Newcomer', 'status' => 'active', 'created_at' => '2026-10-20 00:00:00']);

    expect(collect($this->getJson(ABSENT)->json('data'))->pluck('id')->contains($new->id))->toBeFalse();
});

it('lists only active members', function (): void {
    $inactive = Member::factory()->create(['church_id' => $this->church->id, 'status' => 'inactive', 'created_at' => '2026-01-01 00:00:00']);

    expect(collect($this->getJson(ABSENT)->json('data'))->pluck('id')->contains($inactive->id))->toBeFalse();
});

it('uses a number of services asked for, or the church\'s own setting', function (): void {
    $member = oldMember($this->church, 'Emmanuel');
    attended($this->church, $member, [$this->sundays[2]]);

    // Missed 4 Sundays? No: came to the 3rd. So not absent for 3, but the last 2 (Oct 18 and 25) he came only to Oct 18.
    expect(collect($this->getJson(ABSENT.'?consecutive=2')->json('data'))->pluck('id')->contains($member->id))->toBeFalse();

    $this->church->update(['settings' => ['absence_threshold' => 4]]);
    $this->app->instance('current.church', $this->church->fresh());
    expect(collect($this->getJson(ABSENT)->json('data'))->pluck('id')->contains($member->id))->toBeFalse();
});

it('says nobody is absent when there is not enough history yet', function (): void {
    // Only two Sundays were really held: nobody was checked in to the other two.
    AttendanceRecord::query()->whereIn('service_id', $this->sundays->take(2)->pluck('id'))->forceDelete();

    $this->getJson(ABSENT)->assertOk()->assertJsonPath('meta.total', 0);
});

it('ignores midweek services, which are always smaller', function (): void {
    $member = oldMember($this->church, 'Faithful');
    attended($this->church, $member, [$this->sundays[1], $this->sundays[2]]);
    // Three newer midweek services that the member did not come to must not count against them.
    foreach (['2026-10-23', '2026-10-24', '2026-10-26'] as $date) {
        $midweek = Service::factory()->create(['church_id' => $this->church->id, 'service_date' => $date, 'type' => 'midweek']);
        AttendanceRecord::factory()->create(['church_id' => $this->church->id, 'service_id' => $midweek->id]);
    }

    expect(collect($this->getJson(ABSENT)->json('data'))->pluck('id')->contains($member->id))->toBeFalse();
});

it('ignores a service nobody was checked in to, because it was not held', function (): void {
    $member = oldMember($this->church, 'Faithful');
    attended($this->church, $member, [$this->sundays[1]]);
    // A newer Sunday that nobody was checked in to: it was not held, so it is not a missed service.
    Service::factory()->create(['church_id' => $this->church->id, 'service_date' => '2026-10-26', 'type' => 'sunday']);

    expect(collect($this->getJson(ABSENT)->json('data'))->pluck('id')->contains($member->id))->toBeFalse();
});

it('never lists another church\'s members', function (): void {
    $other = Church::factory()->create();
    $stranger = Member::factory()->create(['church_id' => $other->id, 'status' => 'active', 'created_at' => '2026-01-01 00:00:00']);

    expect(collect($this->getJson(ABSENT)->json('data'))->pluck('id')->contains($stranger->id))->toBeFalse();
});

it('rejects a number of services outside the allowed range', function (string $query): void {
    $this->getJson(ABSENT.$query)->assertUnprocessable()->assertJsonValidationErrors('consecutive');
})->with(['?consecutive=1', '?consecutive=13', '?consecutive=abc']);

it('needs a token, and is for roles that may view reports', function (string $role, int $status): void {
    $staff = User::factory()->for($this->church)->create();
    $staff->assignRole($role);
    Sanctum::actingAs($staff, ['*']);

    $this->getJson(ABSENT)->assertStatus($status);
})->with([['church-admin', 200], ['pastor', 200], ['finance-secretary', 200], ['department-head', 403], ['youth-pastor', 403]]);

it('is refused without a token', function (): void {
    $this->app['auth']->forgetGuards();
    $this->getJson(ABSENT)->assertUnauthorized();
});
