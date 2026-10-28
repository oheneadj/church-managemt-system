<?php

declare(strict_types=1);

use App\Enums\EducationalLevel;
use App\Models\Church;
use App\Models\Department;
use App\Models\Member;
use App\Models\MemberDepartment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

const DEPARTMENTS = 'http://grace.localhost/api/v1/departments';

function deptStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

/** Signs the staff member in for the next request, the way Laravel's tests do. */
function deptAs(User $user): mixed
{
    Sanctum::actingAs($user, ['*']);

    return test();
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->admin = deptStaff($this->church, 'church-admin');
    $this->other = Church::factory()->create(['slug' => 'other']);
});

it('rejects every department endpoint without a token', function (string $method, string $path): void {
    $department = Department::factory()->create(['church_id' => $this->church->id]);
    $member = Member::factory()->create(['church_id' => $this->church->id]);

    $this->json($method, str_replace(['{d}', '{m}'], [$department->id, $member->id], DEPARTMENTS.$path))->assertUnauthorized();
})->with([['GET', ''], ['POST', ''], ['PUT', '/{d}'], ['POST', '/{d}/members'], ['DELETE', '/{d}/members/{m}']]);

describe('listing', function (): void {
    it('returns a paginated list ordered by name with member counts', function (): void {
        $choir = Department::factory()->create(['church_id' => $this->church->id, 'name' => 'Choir']);
        Department::factory()->create(['church_id' => $this->church->id, 'name' => 'Ushers']);
        $member = Member::factory()->create(['church_id' => $this->church->id]);
        MemberDepartment::factory()->create(['church_id' => $this->church->id, 'member_id' => $member->id, 'department_id' => $choir->id]);

        deptAs($this->admin)->getJson(DEPARTMENTS)
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Choir')
            ->assertJsonPath('data.0.members_count', 1)
            ->assertJsonPath('data.1.members_count', 0)
            ->assertJsonStructure(['data', 'meta' => ['total'], 'links']);
    });

    it('filters by active state', function (): void {
        Department::factory()->create(['church_id' => $this->church->id, 'is_active' => true]);
        Department::factory()->create(['church_id' => $this->church->id, 'is_active' => false]);

        deptAs($this->admin)->getJson(DEPARTMENTS.'?is_active=0')->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_active', false);
    });

    it('does not count members the viewer may not see', function (): void {
        $head = deptStaff($this->church, 'department-head');
        $department = Department::factory()->create(['church_id' => $this->church->id, 'head_user_id' => $head->id]);
        $outside = Department::factory()->create(['church_id' => $this->church->id]);
        $member = Member::factory()->create(['church_id' => $this->church->id, 'educational_level' => EducationalLevel::Graduate]);
        MemberDepartment::factory()->create(['church_id' => $this->church->id, 'member_id' => $member->id, 'department_id' => $department->id]);
        MemberDepartment::factory()->create(['church_id' => $this->church->id, 'member_id' => $member->id, 'department_id' => $outside->id]);

        $counts = collect(deptAs($head)->getJson(DEPARTMENTS)->json('data'))->pluck('members_count', 'id');

        expect($counts[$department->id])->toBe(1)->and($counts[$outside->id])->toBe(1);
    });

    it('does not list another church\'s departments', function (): void {
        Department::factory()->create(['church_id' => $this->other->id]);

        deptAs($this->admin)->getJson(DEPARTMENTS)->assertJsonCount(0, 'data');
    });

    it('rejects bad paging', function (): void {
        deptAs($this->admin)->getJson(DEPARTMENTS.'?per_page=500')->assertUnprocessable();
    });

    it('is forbidden without members.view', function (): void {
        deptAs(User::factory()->for($this->church)->create())->getJson(DEPARTMENTS)->assertForbidden();
    });
});

describe('creating', function (): void {
    it('creates a department with a head from this church', function (): void {
        $head = deptStaff($this->church, 'department-head');

        $response = deptAs($this->admin)->postJson(DEPARTMENTS, ['name' => 'Choir', 'description' => 'Sunday choir', 'head_user_id' => $head->id])
            ->assertCreated()->assertJsonPath('data.head_user_id', $head->id)->assertJsonPath('data.is_active', true);

        expect(Department::query()->findOrFail($response->json('data.id'))->church_id)->toBe($this->church->id);
    });

    it('fails validation', function (array $bad, string $field): void {
        deptAs($this->admin)->postJson(DEPARTMENTS, ['name' => 'Choir', ...$bad])->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([
        'no name' => [['name' => ''], 'name'],
        'long name' => [['name' => str_repeat('a', 256)], 'name'],
        'bad head' => [['head_user_id' => 'nope'], 'head_user_id'],
        'bad flag' => [['is_active' => 'maybe'], 'is_active'],
    ]);

    it('refuses a duplicate name in the same church but allows it in another', function (): void {
        Department::factory()->create(['church_id' => $this->church->id, 'name' => 'Choir']);
        Department::factory()->create(['church_id' => $this->other->id, 'name' => 'Ushers']);

        deptAs($this->admin)->postJson(DEPARTMENTS, ['name' => 'Choir'])->assertUnprocessable()->assertJsonValidationErrors('name');
        deptAs($this->admin)->postJson(DEPARTMENTS, ['name' => 'Ushers'])->assertCreated();
    });

    it('refuses a head from another church or one who is deactivated', function (): void {
        $outsider = User::factory()->for($this->other)->create();
        $inactive = User::factory()->for($this->church)->create(['is_active' => false]);

        deptAs($this->admin)->postJson(DEPARTMENTS, ['name' => 'A', 'head_user_id' => $outsider->id])->assertUnprocessable();
        deptAs($this->admin)->postJson(DEPARTMENTS, ['name' => 'B', 'head_user_id' => $inactive->id])->assertUnprocessable();
    });

    it('is forbidden for roles that cannot edit members', function (string $role): void {
        deptAs(deptStaff($this->church, $role))->postJson(DEPARTMENTS, ['name' => 'X'])->assertForbidden();
    })->with(['finance-secretary', 'department-head', 'youth-pastor']);
});

describe('updating', function (): void {
    it('changes only the fields sent, and can retire a department', function (): void {
        $department = Department::factory()->create(['church_id' => $this->church->id, 'name' => 'Choir', 'description' => 'Keep']);

        deptAs($this->admin)->putJson(DEPARTMENTS.'/'.$department->id, ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false)->assertJsonPath('data.name', 'Choir')->assertJsonPath('data.description', 'Keep');
    });

    it('lets a department keep its own name', function (): void {
        $department = Department::factory()->create(['church_id' => $this->church->id, 'name' => 'Choir']);

        deptAs($this->admin)->putJson(DEPARTMENTS.'/'.$department->id, ['name' => 'Choir', 'description' => 'x'])->assertOk();
    });

    it('refuses a name another department already uses', function (): void {
        Department::factory()->create(['church_id' => $this->church->id, 'name' => 'Choir']);
        $ushers = Department::factory()->create(['church_id' => $this->church->id, 'name' => 'Ushers']);

        deptAs($this->admin)->putJson(DEPARTMENTS.'/'.$ushers->id, ['name' => 'Choir'])->assertUnprocessable();
    });

    it('is forbidden for read-only roles', function (): void {
        $department = Department::factory()->create(['church_id' => $this->church->id]);

        deptAs(deptStaff($this->church, 'finance-secretary'))->putJson(DEPARTMENTS.'/'.$department->id, ['name' => 'X'])->assertForbidden();
    });

    it('returns 404 for another church\'s department and leaves it untouched', function (): void {
        $foreign = Department::factory()->create(['church_id' => $this->other->id, 'name' => 'Theirs']);

        deptAs($this->admin)->putJson(DEPARTMENTS.'/'.$foreign->id, ['name' => 'Hacked'])->assertNotFound();

        expect($foreign->fresh()->name)->toBe('Theirs');
    });
});

describe('members of a department', function (): void {
    beforeEach(function (): void {
        $this->department = Department::factory()->create(['church_id' => $this->church->id]);
        $this->member = Member::factory()->create(['church_id' => $this->church->id]);
    });

    it('adds a member with a role', function (): void {
        deptAs($this->admin)->postJson(DEPARTMENTS.'/'.$this->department->id.'/members', ['member_id' => $this->member->id, 'role' => 'Leader'])
            ->assertCreated()
            ->assertJsonPath('data.role', 'Leader')
            ->assertJsonPath('data.member_id', $this->member->id);

        expect($this->member->departments()->count())->toBe(1);
    });

    it('changes the role instead of adding twice', function (): void {
        $url = DEPARTMENTS.'/'.$this->department->id.'/members';
        deptAs($this->admin)->postJson($url, ['member_id' => $this->member->id, 'role' => 'Member'])->assertCreated();

        deptAs($this->admin)->postJson($url, ['member_id' => $this->member->id, 'role' => 'Leader'])->assertOk()->assertJsonPath('data.role', 'Leader');

        expect(MemberDepartment::query()->count())->toBe(1);
    });

    it('adds a member again after they were removed', function (): void {
        $url = DEPARTMENTS.'/'.$this->department->id.'/members';
        deptAs($this->admin)->postJson($url, ['member_id' => $this->member->id])->assertCreated();
        deptAs($this->admin)->deleteJson($url.'/'.$this->member->id)->assertNoContent();

        deptAs($this->admin)->postJson($url, ['member_id' => $this->member->id])->assertCreated();

        expect($this->member->departments()->count())->toBe(1)->and(MemberDepartment::withTrashed()->count())->toBe(2);
    });

    it('refuses an unknown or foreign member', function (): void {
        $foreign = Member::factory()->create(['church_id' => $this->other->id]);
        $url = DEPARTMENTS.'/'.$this->department->id.'/members';

        deptAs($this->admin)->postJson($url, ['member_id' => $foreign->id])->assertUnprocessable()->assertJsonValidationErrors('member_id');
        deptAs($this->admin)->postJson($url, [])->assertUnprocessable();
        expect(MemberDepartment::query()->count())->toBe(0);
    });

    it('removes a member by soft deleting the link', function (): void {
        MemberDepartment::factory()->create(['church_id' => $this->church->id, 'member_id' => $this->member->id, 'department_id' => $this->department->id]);

        deptAs($this->admin)->deleteJson(DEPARTMENTS.'/'.$this->department->id.'/members/'.$this->member->id)->assertNoContent();

        expect($this->member->departments()->count())->toBe(0)->and(MemberDepartment::withTrashed()->count())->toBe(1);
    });

    it('returns 404 when removing someone who is not in the department', function (): void {
        deptAs($this->admin)->deleteJson(DEPARTMENTS.'/'.$this->department->id.'/members/'.$this->member->id)->assertNotFound();
    });

    it('returns 404 for another church\'s department or member', function (): void {
        $foreignDepartment = Department::factory()->create(['church_id' => $this->other->id]);
        $foreignMember = Member::factory()->create(['church_id' => $this->other->id]);
        MemberDepartment::factory()->create(['church_id' => $this->other->id, 'member_id' => $foreignMember->id, 'department_id' => $foreignDepartment->id]);

        deptAs($this->admin)->postJson(DEPARTMENTS.'/'.$foreignDepartment->id.'/members', ['member_id' => $this->member->id])->assertNotFound();
        deptAs($this->admin)->deleteJson(DEPARTMENTS.'/'.$foreignDepartment->id.'/members/'.$foreignMember->id)->assertNotFound();
        deptAs($this->admin)->deleteJson(DEPARTMENTS.'/'.$this->department->id.'/members/'.$foreignMember->id)->assertNotFound();

        expect(MemberDepartment::query()->withoutGlobalScopes()->where('member_id', $foreignMember->id)->count())->toBe(1);
    });

    it('is forbidden for roles that cannot edit members', function (string $role): void {
        $staff = deptStaff($this->church, $role);
        $url = DEPARTMENTS.'/'.$this->department->id.'/members';

        deptAs($staff)->postJson($url, ['member_id' => $this->member->id])->assertForbidden();
        deptAs($staff)->deleteJson($url.'/'.$this->member->id)->assertForbidden();
    })->with(['finance-secretary', 'department-head', 'youth-pastor']);
});
