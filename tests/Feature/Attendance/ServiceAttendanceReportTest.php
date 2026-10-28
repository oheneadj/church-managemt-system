<?php

declare(strict_types=1);

use App\Enums\CheckInMethod;
use App\Enums\StaffRole;
use App\Models\AttendanceRecord;
use App\Models\Church;
use App\Models\Department;
use App\Models\Member;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

// Who checked in to a service, and the headcount (docs/11 story 3.7, docs/08 Get Attendance for a Service,
// QA-05 TC-05-11, QA-10, QA-02). Visitors' names are for staff who may edit members only.

function reportStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->admin = reportStaff($this->church, 'church-admin');
    $this->service = Service::factory()->create(['church_id' => $this->church->id]);
    $this->url = 'http://grace.localhost/api/v1/services/'.$this->service->id.'/attendance';
    Sanctum::actingAs($this->admin, ['*']);
});

/** Checks a new member in to the service by the given method. */
function checkedIn(Service $service, CheckInMethod $method = CheckInMethod::Manual, ?string $at = null): AttendanceRecord
{
    return AttendanceRecord::factory()->create([
        'church_id' => $service->church_id, 'service_id' => $service->id, 'method' => $method, 'checked_in_at' => $at ?? now(),
    ]);
}

it('gives the headcount: everyone, members, visitors, each method and the rate (QA-05 TC-05-11)', function (): void {
    Member::factory()->count(6)->create(['church_id' => $this->church->id, 'status' => 'active']);
    checkedIn($this->service, CheckInMethod::Manual);
    checkedIn($this->service, CheckInMethod::Manual);
    checkedIn($this->service, CheckInMethod::Whatsapp);
    checkedIn($this->service, CheckInMethod::Qr);
    AttendanceRecord::factory()->create(['church_id' => $this->church->id, 'service_id' => $this->service->id, 'member_id' => null, 'visitor_name' => 'Yaw Boateng']);

    $summary = $this->getJson($this->url)->assertOk()->json('summary');

    // 4 members came, and 4 more were made active by the checkins above, so 10 are active.
    expect($summary['total'])->toBe(5)
        ->and($summary['members'])->toBe(4)
        ->and($summary['visitors'])->toBe(1)
        ->and($summary['by_method'])->toBe(['manual' => 3, 'whatsapp' => 1, 'qr' => 1, 'biometric' => 0])
        ->and($summary['active_members'])->toBe(Member::query()->where('status', 'active')->count())
        ->and($summary['attendance_rate'])->toBe(intdiv(4 * 100 + intdiv($summary['active_members'], 2), $summary['active_members']));
});

it('names the service and lists the newest check-in first', function (): void {
    $early = checkedIn($this->service, at: '2026-10-25 09:00:00');
    $late = checkedIn($this->service, at: '2026-10-25 09:30:00');

    $response = $this->getJson($this->url)->assertOk();

    $response->assertJsonPath('service.id', $this->service->id)->assertJsonPath('service.name', $this->service->name);
    expect($response->json('data.0.id'))->toBe($late->id)
        ->and($response->json('data.1.id'))->toBe($early->id)
        ->and($response->json('data.0.member.full_name'))->toBe($late->member->full_name)
        ->and($response->json('data.0.method_label'))->toBe('Manual')
        ->and($response->json('data.0.checked_in_at'))->toEndWith('+00:00');
});

it('lists visitors by name, apart from members', function (): void {
    AttendanceRecord::factory()->create(['church_id' => $this->church->id, 'service_id' => $this->service->id, 'member_id' => null, 'visitor_name' => 'Yaw Boateng']);

    $row = $this->getJson($this->url)->assertOk()->json('data.0');

    expect($row['member'])->toBeNull()->and($row['visitor_name'])->toBe('Yaw Boateng');
});

it('pages the list but counts everyone in the summary', function (): void {
    foreach (range(1, 3) as $i) {
        checkedIn($this->service);
    }

    $response = $this->getJson($this->url.'?per_page=2')->assertOk();

    expect($response->json('data'))->toHaveCount(2)->and($response->json('meta.total'))->toBe(3)->and($response->json('summary.total'))->toBe(3);
});

it('has a zero headcount for a service nobody came to', function (): void {
    $response = $this->getJson($this->url)->assertOk();

    expect($response->json('summary.total'))->toBe(0)->and($response->json('summary.attendance_rate'))->toBe(0)->and($response->json('data'))->toBe([]);
});

describe('what each role sees', function (): void {
    it('shows visitors only to staff who may edit members, and members only within their view', function (): void {
        $department = Department::factory()->create(['church_id' => $this->church->id]);
        $head = reportStaff($this->church, StaffRole::DepartmentHead->value);
        $department->update(['head_user_id' => $head->id]);
        $own = Member::factory()->create(['church_id' => $this->church->id]);
        $own->departments()->attach($department->id, ['id' => (string) Str::ulid(), 'church_id' => $this->church->id, 'joined_at' => now()->toDateString()]);
        $other = Member::factory()->create(['church_id' => $this->church->id]);
        foreach ([$own, $other] as $member) {
            AttendanceRecord::factory()->create(['church_id' => $this->church->id, 'service_id' => $this->service->id, 'member_id' => $member->id]);
        }
        AttendanceRecord::factory()->create(['church_id' => $this->church->id, 'service_id' => $this->service->id, 'member_id' => null, 'visitor_name' => 'Yaw Boateng']);
        Sanctum::actingAs($head, ['*']);

        $response = $this->getJson($this->url)->assertOk();

        expect(collect($response->json('data'))->pluck('member.id')->all())->toBe([$own->id])
            ->and(json_encode($response->json()))->not->toContain('Yaw Boateng')
            // The headcount is a number, not a person, so it still counts everyone.
            ->and($response->json('summary.total'))->toBe(3);
    });

    it('lets every role read the attendance of a service (QA-10)', function (string $role): void {
        Sanctum::actingAs(reportStaff($this->church, $role), ['*']);

        $this->getJson($this->url)->assertOk();
    })->with(['church-admin', 'pastor', 'finance-secretary', 'department-head', 'youth-pastor']);

    it('needs a token', function (): void {
        $this->app['auth']->forgetGuards();
        $this->getJson($this->url)->assertUnauthorized();
    });

    it('is a 404 for another church\'s service (QA-02)', function (): void {
        $theirs = Service::factory()->create();

        $this->getJson('http://grace.localhost/api/v1/services/'.$theirs->id.'/attendance')->assertNotFound();
    });

    it('never counts another church\'s attendance in the summary', function (): void {
        $theirs = Service::factory()->create();
        AttendanceRecord::factory()->count(4)->create(['church_id' => $theirs->church_id, 'service_id' => $theirs->id]);
        checkedIn($this->service);

        $this->getJson($this->url)->assertOk()->assertJsonPath('summary.total', 1);
    });
});
