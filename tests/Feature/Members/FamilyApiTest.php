<?php

declare(strict_types=1);

use App\Enums\EducationalLevel;
use App\Enums\FamilyRole;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Department;
use App\Models\Family;
use App\Models\Member;
use App\Models\MemberDepartment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

const FAMILIES = 'http://grace.localhost/api/v1/families';

function familyStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

/** Signs the staff member in for the next request, the way Laravel's tests do. */
function familyAs(User $user): mixed
{
    Sanctum::actingAs($user, ['*']);

    return test();
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace', 'member_number_prefix' => 'GRC']);
    $this->admin = familyStaff($this->church, 'church-admin');
    $this->other = Church::factory()->create(['slug' => 'other']);
});

it('rejects every family endpoint without a token', function (string $method, string $path): void {
    $family = Family::factory()->create(['church_id' => $this->church->id]);

    $this->json($method, str_replace('{id}', $family->id, FAMILIES.$path))->assertUnauthorized();
})->with([['GET', ''], ['POST', ''], ['GET', '/{id}'], ['PUT', '/{id}']]);

describe('listing', function (): void {
    it('returns a paginated list with member counts', function (): void {
        $family = Family::factory()->create(['church_id' => $this->church->id, 'name' => 'Mensah Family']);
        Member::factory()->count(2)->create(['church_id' => $this->church->id, 'family_id' => $family->id, 'family_role' => FamilyRole::Child]);

        familyAs($this->admin)->getJson(FAMILIES)
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Mensah Family')
            ->assertJsonPath('data.0.members_count', 2)
            ->assertJsonStructure(['data', 'meta' => ['total'], 'links']);
    });

    it('searches by name, ignoring case', function (): void {
        Family::factory()->create(['church_id' => $this->church->id, 'name' => 'Mensah Family']);
        Family::factory()->create(['church_id' => $this->church->id, 'name' => 'Asante Family']);

        familyAs($this->admin)->getJson(FAMILIES.'?search=mensah')->assertJsonCount(1, 'data');
        familyAs($this->admin)->getJson(FAMILIES.'?search='.urlencode('%'))->assertJsonCount(0, 'data');
    });

    it('rejects a page size above 100', function (): void {
        familyAs($this->admin)->getJson(FAMILIES.'?per_page=101')->assertUnprocessable();
    });

    it('does not list or count another church\'s families', function (): void {
        Family::factory()->create(['church_id' => $this->other->id]);

        familyAs($this->admin)->getJson(FAMILIES)->assertJsonCount(0, 'data');
    });

    it('counts only the members the viewer may see', function (): void {
        $head = familyStaff($this->church, 'department-head');
        $family = Family::factory()->create(['church_id' => $this->church->id]);
        $department = Department::factory()->create(['church_id' => $this->church->id, 'head_user_id' => $head->id]);
        $visible = Member::factory()->create(['church_id' => $this->church->id, 'family_id' => $family->id, 'family_role' => FamilyRole::Child, 'educational_level' => EducationalLevel::Graduate]);
        Member::factory()->create(['church_id' => $this->church->id, 'family_id' => $family->id, 'family_role' => FamilyRole::Child, 'educational_level' => EducationalLevel::Graduate]);
        MemberDepartment::factory()->create(['church_id' => $this->church->id, 'member_id' => $visible->id, 'department_id' => $department->id]);

        familyAs($head)->getJson(FAMILIES)->assertJsonPath('data.0.members_count', 1);
    });

    it('is forbidden without members.view', function (): void {
        familyAs(User::factory()->for($this->church)->create())->getJson(FAMILIES)->assertForbidden();
    });
});

describe('showing', function (): void {
    it('returns the family with its members', function (): void {
        $family = Family::factory()->create(['church_id' => $this->church->id]);
        $member = Member::factory()->create(['church_id' => $this->church->id, 'family_id' => $family->id, 'family_role' => FamilyRole::Head]);

        familyAs($this->admin)->getJson(FAMILIES.'/'.$family->id)
            ->assertOk()
            ->assertJsonPath('data.members.0.id', $member->id)
            ->assertJsonPath('data.members.0.family_role', 'head')
            ->assertJsonMissingPath('data.members.0.phone');
    });

    it('lists only the members the viewer may see', function (): void {
        $head = familyStaff($this->church, 'department-head');
        $family = Family::factory()->create(['church_id' => $this->church->id]);
        $department = Department::factory()->create(['church_id' => $this->church->id, 'head_user_id' => $head->id]);
        $mine = Member::factory()->create(['church_id' => $this->church->id, 'family_id' => $family->id, 'family_role' => FamilyRole::Child, 'educational_level' => EducationalLevel::Graduate]);
        Member::factory()->create(['church_id' => $this->church->id, 'family_id' => $family->id, 'family_role' => FamilyRole::Child, 'educational_level' => EducationalLevel::Graduate]);
        MemberDepartment::factory()->create(['church_id' => $this->church->id, 'member_id' => $mine->id, 'department_id' => $department->id]);

        familyAs($head)->getJson(FAMILIES.'/'.$family->id)->assertJsonCount(1, 'data.members')->assertJsonPath('data.members.0.id', $mine->id);
    });

    it('returns 404 for another church\'s family', function (): void {
        $foreign = Family::factory()->create(['church_id' => $this->other->id]);

        familyAs($this->admin)->getJson(FAMILIES.'/'.$foreign->id)->assertNotFound();
    });
});

describe('creating', function (): void {
    it('creates a family', function (): void {
        $response = familyAs($this->admin)->postJson(FAMILIES, ['name' => 'Boateng Family', 'notes' => 'Moved from Kumasi'])
            ->assertCreated()->assertJsonPath('data.name', 'Boateng Family');

        expect(Family::query()->findOrFail($response->json('data.id'))->church_id)->toBe($this->church->id);
    });

    it('makes the chosen member the head, and logs the change on the member', function (): void {
        $member = Member::factory()->create(['church_id' => $this->church->id]);

        $response = familyAs($this->admin)->postJson(FAMILIES, ['name' => 'Boateng Family', 'head_member_id' => $member->id])->assertCreated();

        expect($member->fresh()->family_id)->toBe($response->json('data.id'))
            ->and($member->fresh()->family_role)->toBe(FamilyRole::Head)
            ->and($response->json('data.head_member_id'))->toBe($member->id)
            ->and(AuditLog::query()->where('auditable_id', $member->id)->count())->toBe(1);
    });

    it('does not move a member who already belongs to a family', function (): void {
        $existing = Family::factory()->create(['church_id' => $this->church->id]);
        $member = Member::factory()->create(['church_id' => $this->church->id, 'family_id' => $existing->id, 'family_role' => FamilyRole::Spouse]);

        familyAs($this->admin)->postJson(FAMILIES, ['name' => 'New', 'head_member_id' => $member->id])
            ->assertUnprocessable()->assertJsonValidationErrors('head_member_id');

        expect(Family::query()->count())->toBe(1)->and($member->fresh()->family_id)->toBe($existing->id);
    });

    it('refuses another church\'s member as head', function (): void {
        $foreign = Member::factory()->create(['church_id' => $this->other->id]);

        familyAs($this->admin)->postJson(FAMILIES, ['name' => 'X', 'head_member_id' => $foreign->id])
            ->assertUnprocessable()->assertJsonValidationErrors('head_member_id');

        expect($foreign->fresh()->family_id)->toBeNull();
    });

    it('fails validation', function (): void {
        familyAs($this->admin)->postJson(FAMILIES, [])->assertUnprocessable()->assertJsonValidationErrors('name');
    });

    it('is forbidden for roles that cannot edit members', function (string $role): void {
        familyAs(familyStaff($this->church, $role))->postJson(FAMILIES, ['name' => 'X'])->assertForbidden();
    })->with(['finance-secretary', 'department-head', 'youth-pastor']);

    it('lets the pastor create families', function (): void {
        familyAs(familyStaff($this->church, 'pastor'))->postJson(FAMILIES, ['name' => 'X'])->assertCreated();
    });
});

describe('updating', function (): void {
    it('renames a family', function (): void {
        $family = Family::factory()->create(['church_id' => $this->church->id, 'name' => 'Old']);

        familyAs($this->admin)->putJson(FAMILIES.'/'.$family->id, ['name' => 'New'])->assertOk()->assertJsonPath('data.name', 'New');
    });

    it('does not let the head be changed here', function (): void {
        $family = Family::factory()->create(['church_id' => $this->church->id]);
        $member = Member::factory()->create(['church_id' => $this->church->id]);

        familyAs($this->admin)->putJson(FAMILIES.'/'.$family->id, ['head_member_id' => $member->id, 'name' => 'Same'])->assertOk();

        expect($family->fresh()->head_member_id)->toBeNull();
    });

    it('fails validation', function (): void {
        $family = Family::factory()->create(['church_id' => $this->church->id]);

        familyAs($this->admin)->putJson(FAMILIES.'/'.$family->id, ['name' => ''])->assertUnprocessable();
    });

    it('is forbidden for read-only roles', function (): void {
        $family = Family::factory()->create(['church_id' => $this->church->id]);

        familyAs(familyStaff($this->church, 'finance-secretary'))->putJson(FAMILIES.'/'.$family->id, ['name' => 'X'])->assertForbidden();
    });

    it('returns 404 for another church\'s family and leaves it untouched', function (): void {
        $foreign = Family::factory()->create(['church_id' => $this->other->id, 'name' => 'Theirs']);

        familyAs($this->admin)->putJson(FAMILIES.'/'.$foreign->id, ['name' => 'Hacked'])->assertNotFound();

        expect($foreign->fresh()->name)->toBe('Theirs');
    });
});
