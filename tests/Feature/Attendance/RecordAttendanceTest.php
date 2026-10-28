<?php

declare(strict_types=1);

use App\Enums\CheckInMethod;
use App\Enums\MemberStatus;
use App\Models\AttendanceRecord;
use App\Models\Church;
use App\Models\Member;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

// Manual bulk check-in (docs/11 story 3.2 and 3.5, docs/08 Record Attendance, QA-05 TC-05-07/08/14,
// QA-02 TC-02-09, QA-10). The unique index makes a second check-in a no-op.

/** A staff member of the church with one role. */
function attendanceStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->admin = attendanceStaff($this->church, 'church-admin');
    $this->service = Service::factory()->open()->create(['church_id' => $this->church->id]);
    $this->members = Member::factory()->count(3)->create(['church_id' => $this->church->id]);
    $this->url = 'http://grace.localhost/api/v1/services/'.$this->service->id.'/attendance';
    Sanctum::actingAs($this->admin, ['*']);
});

it('records the members present and says how many were new', function (): void {
    $this->postJson($this->url, ['member_ids' => $this->members->pluck('id')->all(), 'method' => 'manual'])
        ->assertCreated()
        ->assertExactJson(['recorded' => 3, 'already_checked_in' => 0, 'errors' => []]);

    $records = AttendanceRecord::query()->where('service_id', $this->service->id)->get();
    expect($records)->toHaveCount(3)->and($records->every(fn ($r) => $r->method === CheckInMethod::Manual))->toBeTrue();
});

it('counts a second check-in as already checked in and adds no row (QA-05 TC-05-07)', function (): void {
    $ids = $this->members->pluck('id')->all();
    $this->postJson($this->url, ['member_ids' => $ids])->assertCreated();

    $this->postJson($this->url, ['member_ids' => $ids])->assertCreated()->assertExactJson(['recorded' => 0, 'already_checked_in' => 3, 'errors' => []]);

    expect(AttendanceRecord::query()->where('service_id', $this->service->id)->count())->toBe(3);
});

it('records only the new ones when some are already in', function (): void {
    $ids = $this->members->pluck('id')->all();
    $this->postJson($this->url, ['member_ids' => [$ids[0]]])->assertCreated();

    $this->postJson($this->url, ['member_ids' => $ids])->assertCreated()->assertJsonPath('recorded', 2)->assertJsonPath('already_checked_in', 1);
});

it('ignores a member named twice in one request', function (): void {
    $id = $this->members->first()->id;

    $this->postJson($this->url, ['member_ids' => [$id, $id]])->assertUnprocessable()->assertJsonValidationErrors('member_ids.0');
});

it('lets a member attend two services on one day (QA-05 TC-05-13)', function (): void {
    $second = Service::factory()->open()->create(['church_id' => $this->church->id, 'service_date' => $this->service->service_date]);
    $ids = [$this->members->first()->id];

    $this->postJson($this->url, ['member_ids' => $ids])->assertCreated()->assertJsonPath('recorded', 1);
    $this->postJson('http://grace.localhost/api/v1/services/'.$second->id.'/attendance', ['member_ids' => $ids])->assertCreated()->assertJsonPath('recorded', 1);
});

it('works on a closed service, so a missed person can be added afterwards (QA-05 TC-05-14)', function (): void {
    $this->service->update(['is_open' => false]);

    $this->postJson($this->url, ['member_ids' => [$this->members->first()->id]])->assertCreated()->assertJsonPath('recorded', 1);
});

it('reports a member who is not found without stopping the others', function (): void {
    $unknown = '01hxxxxxxxxxxxxxxxxxxxxxxx';

    $response = $this->postJson($this->url, ['member_ids' => [$this->members->first()->id, $unknown]])->assertCreated();

    $response->assertJsonPath('recorded', 1)->assertJsonPath('errors.0.member_id', $unknown)->assertJsonPath('errors.0.message', 'Member not found.');
});

it('does not check in a member of another church, and does not reveal that they exist', function (): void {
    $stranger = Member::factory()->create();

    $response = $this->postJson($this->url, ['member_ids' => [$stranger->id]])->assertCreated();

    $response->assertJsonPath('recorded', 0)->assertJsonPath('errors.0.message', 'Member not found.');
    expect(AttendanceRecord::query()->withoutGlobalScopes()->where('member_id', $stranger->id)->count())->toBe(0);
});

it('does not check in a member who has died', function (): void {
    $deceased = Member::factory()->create(['church_id' => $this->church->id, 'status' => MemberStatus::Deceased]);

    $this->postJson($this->url, ['member_ids' => [$deceased->id]])->assertCreated()->assertJsonPath('recorded', 0)->assertJsonPath('errors.0.message', 'This member cannot be checked in.');
});

it('does allow an inactive member to be checked in, because they came', function (): void {
    $inactive = Member::factory()->create(['church_id' => $this->church->id, 'status' => MemberStatus::Inactive]);

    $this->postJson($this->url, ['member_ids' => [$inactive->id]])->assertCreated()->assertJsonPath('recorded', 1);
});

it('stamps the time in UTC', function (): void {
    $this->travelTo('2026-10-25 09:15:00');

    $this->postJson($this->url, ['member_ids' => [$this->members->first()->id]])->assertCreated();

    expect(AttendanceRecord::query()->sole()->checked_in_at->toIso8601String())->toBe('2026-10-25T09:15:00+00:00');
});

it('rejects bad input', function (array $body, string $field): void {
    $this->postJson($this->url, $body)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'no members' => [[], 'member_ids'],
    'empty list' => [['member_ids' => []], 'member_ids'],
    'not a ulid' => [['member_ids' => ['12']], 'member_ids.0'],
    'another method' => [['member_ids' => ['01hxxxxxxxxxxxxxxxxxxxxxxx'], 'method' => 'whatsapp'], 'method'],
]);

it('refuses a list longer than the limit', function (): void {
    config(['attendance.bulk_max' => 2]);

    $this->postJson($this->url, ['member_ids' => $this->members->pluck('id')->all()])->assertUnprocessable()->assertJsonValidationErrors('member_ids');
});

describe('who may', function (): void {
    it('needs a token', function (): void {
        $this->app['auth']->forgetGuards();
        $this->postJson($this->url, ['member_ids' => ['01hxxxxxxxxxxxxxxxxxxxxxxx']])->assertUnauthorized();
    });

    it('is allowed to the church admin and the pastor and refused to everyone else (QA-10)', function (string $role, int $status): void {
        Sanctum::actingAs(attendanceStaff($this->church, $role), ['*']);

        $this->postJson($this->url, ['member_ids' => [$this->members->first()->id]])->assertStatus($status);
    })->with([['church-admin', 201], ['pastor', 201], ['finance-secretary', 403], ['department-head', 403], ['youth-pastor', 403]]);

    it('is a 404 for another church\'s service and records nothing (QA-02 TC-02-09)', function (): void {
        $other = Church::factory()->create();
        $theirs = Service::factory()->create(['church_id' => $other->id]);

        $this->postJson('http://grace.localhost/api/v1/services/'.$theirs->id.'/attendance', ['member_ids' => [$this->members->first()->id]])->assertNotFound();
        expect(AttendanceRecord::query()->withoutGlobalScopes()->where('service_id', $theirs->id)->count())->toBe(0);
    });
});
