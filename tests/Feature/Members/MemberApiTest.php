<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Enums\EducationalLevel;
use App\Enums\FamilyRole;
use App\Enums\MemberStatus;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\Department;
use App\Models\Family;
use App\Models\Member;
use App\Models\MemberDepartment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

const MEMBERS = 'http://grace.localhost/api/v1/members';

/** A staff member of the church holding one role, with a bearer token ready to send. */
function staffWithRole(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create(['name' => 'Staff '.$role]);
    $user->assignRole($role);

    return $user;
}

/** Signs the staff member in for the next request, the way Laravel's tests do. */
function as_(User $user): mixed
{
    Sanctum::actingAs($user, ['*']);

    return test();
}

function newMember(Church $church, array $attributes = []): Member
{
    return Member::factory()->create(['church_id' => $church->id, ...$attributes]);
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace', 'member_number_prefix' => 'GRC']);
    $this->admin = staffWithRole($this->church, 'church-admin');
    $this->other = Church::factory()->create(['slug' => 'other']);
});

function payload(array $overrides = []): array
{
    return [
        'first_name' => 'Abena',
        'last_name' => 'Asante',
        'gender' => 'female',
        'phone' => '0200987654',
        ...$overrides,
    ];
}

describe('authentication', function (): void {
    it('rejects every endpoint without a token', function (string $method, string $path): void {
        $member = newMember($this->church);

        $this->json($method, str_replace('{id}', $member->id, MEMBERS.$path))->assertUnauthorized();
    })->with([
        ['GET', ''],
        ['POST', ''],
        ['GET', '/{id}'],
        ['PUT', '/{id}'],
        ['PATCH', '/{id}/status'],
    ]);
});

describe('listing', function (): void {
    it('returns a paginated directory in the documented envelope', function (): void {
        Member::factory()->count(3)->create(['church_id' => $this->church->id]);

        as_($this->admin)->getJson(MEMBERS)
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'member_number', 'full_name', 'preferred_name', 'gender', 'phone', 'status', 'departments', 'family_id', 'membership_date']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ])
            ->assertJsonPath('meta.per_page', 25);
    });

    it('never exposes a database key other than the ULID', function (): void {
        newMember($this->church);

        $id = as_($this->admin)->getJson(MEMBERS)->json('data.0.id');

        expect($id)->toMatch('/^[0-9a-z]{26}$/');
    });

    it('hides inactive members from the default view but keeps them searchable', function (): void {
        newMember($this->church, ['first_name' => 'Kwame', 'status' => MemberStatus::Active]);
        newMember($this->church, ['first_name' => 'Kofi', 'status' => MemberStatus::Inactive]);

        as_($this->admin)->getJson(MEMBERS)->assertJsonCount(1, 'data')->assertJsonPath('data.0.full_name', fn ($name) => str_contains($name, 'Kwame'));
        as_($this->admin)->getJson(MEMBERS.'?search=Kofi')->assertJsonCount(1, 'data');
        as_($this->admin)->getJson(MEMBERS.'?status=inactive')->assertJsonCount(1, 'data');
    });

    it('finds a member by any word of the name, phone or member number, ignoring case', function (): void {
        newMember($this->church, ['first_name' => 'Emmanuel', 'last_name' => 'Mensah-Boateng', 'phone' => '+233244123456', 'member_number' => 'GRC-0042']);
        newMember($this->church, ['first_name' => 'Someone', 'last_name' => 'Else']);

        foreach (['emmanuel mensah', 'BOATENG', '244123', 'grc-0042'] as $term) {
            as_($this->admin)->getJson(MEMBERS.'?search='.urlencode($term))->assertJsonCount(1, 'data');
        }
    });

    it('finds a member by their phone number written in the local form', function (): void {
        $member = newMember($this->church, ['phone' => '+233201234567']);
        newMember($this->church, ['phone' => '+233209999999']);

        foreach (['0201234567', '233201234567', '+233201234567'] as $typed) {
            as_($this->admin)->getJson(MEMBERS.'?search='.urlencode($typed))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $member->id);
        }
    });

    it('treats percent and underscore in a search as plain characters', function (): void {
        newMember($this->church, ['first_name' => 'Alpha']);

        as_($this->admin)->getJson(MEMBERS.'?search='.urlencode('%'))->assertJsonCount(0, 'data');
        as_($this->admin)->getJson(MEMBERS.'?search='.urlencode('_lpha'))->assertJsonCount(0, 'data');
    });

    it('filters by gender, family and department', function (): void {
        $family = Family::factory()->create(['church_id' => $this->church->id]);
        $choir = Department::factory()->create(['church_id' => $this->church->id]);
        $sister = newMember($this->church, ['gender' => 'female', 'family_id' => $family->id, 'family_role' => FamilyRole::Child]);
        newMember($this->church, ['gender' => 'male']);
        MemberDepartment::factory()->create(['church_id' => $this->church->id, 'member_id' => $sister->id, 'department_id' => $choir->id]);

        as_($this->admin)->getJson(MEMBERS.'?gender=female')->assertJsonCount(1, 'data');
        as_($this->admin)->getJson(MEMBERS.'?family_id='.$family->id)->assertJsonCount(1, 'data');
        as_($this->admin)->getJson(MEMBERS.'?department_id='.$choir->id)->assertJsonCount(1, 'data')->assertJsonPath('data.0.departments.0', $choir->name);
    });

    it('sorts and pages', function (): void {
        newMember($this->church, ['last_name' => 'Boateng']);
        newMember($this->church, ['last_name' => 'Asante']);
        newMember($this->church, ['last_name' => 'Cobbinah']);

        as_($this->admin)->getJson(MEMBERS.'?sort=last_name&direction=desc&per_page=2')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.full_name', fn ($n) => str_ends_with($n, 'Cobbinah'));
    });

    it('rejects bad filters', function (string $query): void {
        as_($this->admin)->getJson(MEMBERS.'?'.$query)->assertUnprocessable();
    })->with(['per_page=101', 'per_page=0', 'sort=password', 'direction=sideways', 'status=nonsense', 'department_id=1']);

    it('answers in a constant number of queries however many members there are', function (): void {
        Member::factory()->count(30)->create(['church_id' => $this->church->id]);
        as_($this->admin)->getJson(MEMBERS); // the first call warms the permission cache

        DB::enableQueryLog();
        as_($this->admin)->getJson(MEMBERS)->assertOk();

        expect(count(DB::getQueryLog()))->toBeLessThan(15);
    });

    it('shows each staff role only what the directory rules allow', function (): void {
        $head = staffWithRole($this->church, 'department-head');
        $youthPastor = staffWithRole($this->church, 'youth-pastor');
        $ownDepartment = Department::factory()->create(['church_id' => $this->church->id, 'head_user_id' => $head->id]);
        $otherDepartment = Department::factory()->create(['church_id' => $this->church->id]);
        $inMine = newMember($this->church, ['educational_level' => EducationalLevel::Graduate]);
        $inTheirs = newMember($this->church, ['educational_level' => EducationalLevel::Graduate]);
        $schoolChild = newMember($this->church, ['educational_level' => EducationalLevel::Jhs2]);
        $adult = newMember($this->church, ['educational_level' => EducationalLevel::Graduate]);
        MemberDepartment::factory()->create(['church_id' => $this->church->id, 'member_id' => $inMine->id, 'department_id' => $ownDepartment->id]);
        MemberDepartment::factory()->create(['church_id' => $this->church->id, 'member_id' => $inTheirs->id, 'department_id' => $otherDepartment->id]);

        as_($head)->getJson(MEMBERS)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $inMine->id);
        as_($youthPastor)->getJson(MEMBERS)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $schoolChild->id);
        as_(staffWithRole($this->church, 'pastor'))->getJson(MEMBERS)->assertJsonCount(4, 'data');
        as_(staffWithRole($this->church, 'finance-secretary'))->getJson(MEMBERS)->assertJsonCount(4, 'data');
        expect($adult->exists)->toBeTrue();
    });

    it('does not list another church\'s members', function (): void {
        newMember($this->other);
        newMember($this->church);

        as_($this->admin)->getJson(MEMBERS)->assertJsonCount(1, 'data');
    });

    it('is forbidden without members.view', function (): void {
        $nobody = User::factory()->for($this->church)->create();

        as_($nobody)->getJson(MEMBERS)->assertForbidden();
    });
});

describe('the youth pastor\'s view', function (): void {
    beforeEach(function (): void {
        $this->youthPastor = staffWithRole($this->church, 'youth-pastor');
    });

    it('goes by birth date: younger than the age limit is youth, whatever the level says', function (): void {
        $young = newMember($this->church, ['date_of_birth' => today()->subYears(20), 'educational_level' => EducationalLevel::Graduate]);
        $old = newMember($this->church, ['date_of_birth' => today()->subYears(40), 'educational_level' => EducationalLevel::Jhs1]);

        $ids = collect(as_($this->youthPastor)->getJson(MEMBERS)->json('data'))->pluck('id');

        expect($ids)->toContain($young->id)->not->toContain($old->id);
    });

    it('draws the line at the default of 25', function (): void {
        $justUnder = newMember($this->church, ['date_of_birth' => today()->subYears(25)->addDay()]);
        $exactly = newMember($this->church, ['date_of_birth' => today()->subYears(25)]);

        $ids = collect(as_($this->youthPastor)->getJson(MEMBERS)->json('data'))->pluck('id');

        expect($ids)->toContain($justUnder->id)->not->toContain($exactly->id);
    });

    it('uses the church\'s own age limit when it sets one', function (): void {
        $this->church->update(['settings' => ['youth_age_limit' => 18]]);
        $nineteen = newMember($this->church, ['date_of_birth' => today()->subYears(19)]);
        $seventeen = newMember($this->church, ['date_of_birth' => today()->subYears(17)]);

        $ids = collect(as_($this->youthPastor)->getJson(MEMBERS)->json('data'))->pluck('id');

        expect($ids)->toContain($seventeen->id)->not->toContain($nineteen->id);
    });

    it('falls back to the educational level when there is no birth date', function (): void {
        $student = newMember($this->church, ['date_of_birth' => null, 'educational_level' => EducationalLevel::Shs2]);
        $adult = newMember($this->church, ['date_of_birth' => null, 'educational_level' => EducationalLevel::Graduate]);
        $unknown = newMember($this->church, ['date_of_birth' => null, 'educational_level' => null]);

        $ids = collect(as_($this->youthPastor)->getJson(MEMBERS)->json('data'))->pluck('id');

        expect($ids)->toContain($student->id)->not->toContain($adult->id)->not->toContain($unknown->id);
    });

    it('does not use another church\'s age limit', function (): void {
        $this->other->update(['settings' => ['youth_age_limit' => 60]]);
        $thirty = newMember($this->church, ['date_of_birth' => today()->subYears(30)]);

        $ids = collect(as_($this->youthPastor)->getJson(MEMBERS)->json('data'))->pluck('id');

        expect($ids)->not->toContain($thirty->id);
    });
});

describe('showing', function (): void {
    it('returns the full record', function (): void {
        $family = Family::factory()->create(['church_id' => $this->church->id, 'name' => 'Mensah Family']);
        $member = newMember($this->church, [
            'address' => '12 Ring Road',
            'family_id' => $family->id,
            'family_role' => FamilyRole::Head,
            'date_of_birth' => '1985-07-12',
            'membership_date' => '2023-04-15',
        ]);

        as_($this->admin)->getJson(MEMBERS.'/'.$member->id)
            ->assertOk()
            ->assertJsonPath('data.id', $member->id)
            ->assertJsonPath('data.address', '12 Ring Road')
            ->assertJsonPath('data.date_of_birth', '1985-07-12')
            ->assertJsonPath('data.family.name', 'Mensah Family')
            ->assertJsonPath('data.family.role', 'head')
            ->assertJsonPath('data.attendance_summary.last_4_weeks', 0)
            ->assertJsonMissingPath('data.national_id_number')
            ->assertJsonMissingPath('data.church_id');
    });

    it('sums giving as exact decimal strings and ignores voided gifts', function (): void {
        $member = newMember($this->church);
        Contribution::factory()->create(['church_id' => $this->church->id, 'member_id' => $member->id, 'amount' => '100.10', 'contributed_at' => now()]);
        Contribution::factory()->create(['church_id' => $this->church->id, 'member_id' => $member->id, 'amount' => '200.20', 'contributed_at' => now()]);
        Contribution::factory()->create(['church_id' => $this->church->id, 'member_id' => $member->id, 'amount' => '999.00', 'contributed_at' => now(), 'is_voided' => true]);

        as_($this->admin)->getJson(MEMBERS.'/'.$member->id)
            ->assertJsonPath('data.giving_summary.all_time', '300.30')
            ->assertJsonPath('data.giving_summary.this_month', '300.30');
    });

    it('sends a label beside every enum value and a formatted amount beside every total', function (): void {
        $member = newMember($this->church, ['gender' => 'female', 'educational_level' => EducationalLevel::Jhs2]);
        Contribution::factory()->create(['church_id' => $this->church->id, 'member_id' => $member->id, 'amount' => '2400.5', 'contributed_at' => now()]);

        as_($this->admin)->getJson(MEMBERS.'/'.$member->id)
            ->assertJsonPath('data.gender', 'female')
            ->assertJsonPath('data.gender_label', 'Female')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.status_label', 'Active')
            ->assertJsonPath('data.educational_level_label', 'JHS 2')
            ->assertJsonPath('data.giving_summary.all_time', '2400.50')
            ->assertJsonPath('data.giving_summary.all_time_formatted', 'GHS 2,400.50');
    });

    it('puts a line in the change log each time the record is opened', function (): void {
        $member = newMember($this->church);

        as_($this->admin)->getJson(MEMBERS.'/'.$member->id)->assertOk();

        $log = AuditLog::query()->where('auditable_id', $member->id)->sole();
        expect($log->event)->toBe(AuditEvent::Viewed)
            ->and($log->actor_id)->toBe($this->admin->id)
            ->and($log->new_values)->toBe(['address' => '[viewed]']);
    });

    it('does not log a view that was refused', function (): void {
        $head = staffWithRole($this->church, 'department-head');
        $member = newMember($this->church);

        as_($head)->getJson(MEMBERS.'/'.$member->id)->assertForbidden();

        expect(AuditLog::query()->count())->toBe(0);
    });

    it('leaves the giving summary out for roles without giving.summary', function (): void {
        $member = newMember($this->church, ['educational_level' => EducationalLevel::Jhs1]);

        as_(staffWithRole($this->church, 'youth-pastor'))->getJson(MEMBERS.'/'.$member->id)
            ->assertOk()
            ->assertJsonMissingPath('data.giving_summary');
    });

    it('is forbidden for a member outside the viewer\'s department', function (): void {
        $head = staffWithRole($this->church, 'department-head');
        $member = newMember($this->church);

        as_($head)->getJson(MEMBERS.'/'.$member->id)->assertForbidden();
    });

    it('returns 404 for another church\'s member', function (): void {
        $foreign = newMember($this->other);

        as_($this->admin)->getJson(MEMBERS.'/'.$foreign->id)->assertNotFound();
    });

    it('returns 404 for a deleted member', function (): void {
        $member = newMember($this->church);
        $member->delete();

        as_($this->admin)->getJson(MEMBERS.'/'.$member->id)->assertNotFound();
    });
});

describe('creating', function (): void {
    it('adds a member with the next member number and records who did it', function (): void {
        $response = as_($this->admin)->postJson(MEMBERS, payload(['middle_name' => 'Akua']))->assertCreated();

        $response->assertJsonPath('data.member_number', 'GRC-0001')
            ->assertJsonPath('data.phone', '+233200987654')
            ->assertJsonPath('data.status', 'active');

        $member = Member::query()->findOrFail($response->json('data.id'));
        expect($member->created_by)->toBe($this->admin->id)
            ->and($member->status_changed_at)->not->toBeNull();

        $log = AuditLog::query()->where('auditable_id', $member->id)->sole();
        expect($log->event)->toBe(AuditEvent::Created)->and($log->actor_name)->toBe($this->admin->name);
    });

    it('numbers the next member GRC-0002', function (): void {
        as_($this->admin)->postJson(MEMBERS, payload())->assertCreated();

        as_($this->admin)->postJson(MEMBERS, payload(['phone' => '0200000002']))->assertJsonPath('data.member_number', 'GRC-0002');
    });

    it('lets the pastor add members', function (): void {
        as_(staffWithRole($this->church, 'pastor'))->postJson(MEMBERS, payload())->assertCreated();
    });

    it('keeps encrypted fields out of the change log and the database in clear text', function (): void {
        $id = as_($this->admin)->postJson(MEMBERS, payload(['address' => 'Secret Street 1', 'national_id_number' => 'GHA-123']))->json('data.id');

        $log = AuditLog::query()->where('auditable_id', $id)->sole();
        expect(json_encode($log->new_values))->not->toContain('Secret Street')->not->toContain('GHA-123')
            ->and($log->new_values['address'])->toBe('[changed]');
        expect(DB::table('members')->where('id', $id)->value('address'))->not->toContain('Secret Street');
    });

    it('attaches departments from this church', function (): void {
        $choir = Department::factory()->create(['church_id' => $this->church->id]);

        $response = as_($this->admin)->postJson(MEMBERS, payload(['department_ids' => [$choir->id]]))->assertCreated();

        $response->assertJsonPath('data.departments.0.id', $choir->id);
    });

    it('makes the first person to claim it the head of the family', function (): void {
        $family = Family::factory()->create(['church_id' => $this->church->id]);

        $id = as_($this->admin)->postJson(MEMBERS, payload(['family_id' => $family->id, 'family_role' => 'head']))->assertCreated()->json('data.id');

        expect($family->fresh()->head_member_id)->toBe($id);
    });

    it('refuses a second head of household', function (): void {
        $family = Family::factory()->create(['church_id' => $this->church->id]);
        newMember($this->church, ['family_id' => $family->id, 'family_role' => FamilyRole::Head]);

        as_($this->admin)->postJson(MEMBERS, payload(['family_id' => $family->id, 'family_role' => 'head']))
            ->assertUnprocessable()->assertJsonValidationErrors('family_role');
    });

    it('is forbidden for roles that cannot edit members', function (string $role): void {
        as_(staffWithRole($this->church, $role))->postJson(MEMBERS, payload())->assertForbidden();
    })->with(['finance-secretary', 'department-head', 'youth-pastor']);

    it('fails validation with the documented error envelope', function (): void {
        as_($this->admin)->postJson(MEMBERS, [])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['first_name', 'last_name', 'gender', 'phone']]);
    });

    it('rejects bad values', function (array $bad, string $field): void {
        as_($this->admin)->postJson(MEMBERS, payload($bad))->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([
        'gender' => [['gender' => 'robot'], 'gender'],
        'phone' => [['phone' => 'abc'], 'phone'],
        'future birth date' => [['date_of_birth' => '2999-01-01'], 'date_of_birth'],
        'birth date format' => [['date_of_birth' => '12/07/1985'], 'date_of_birth'],
        'email' => [['email' => 'nope'], 'email'],
        'status' => [['status' => 'deceased'], 'status'],
        'family role without family' => [['family_role' => 'head'], 'family_id'],
        'unknown department' => [['department_ids' => ['01jzzzzzzzzzzzzzzzzzzzzzzz']], 'department_ids.0'],
    ]);

    it('refuses a phone already used by a member of this church', function (): void {
        newMember($this->church, ['phone' => '+233200987654']);

        as_($this->admin)->postJson(MEMBERS, payload())->assertUnprocessable()->assertJsonValidationErrors('phone');
    });

    it('says in plain words that the phone number is already used', function () {
        newMember($this->church, ['phone' => '+233200987654']);

        as_($this->admin)->postJson(MEMBERS, payload())->assertJsonPath('errors.phone.0', 'A member with this phone number already exists.');
    });

    it('accepts a phone used only in another church', function (): void {
        newMember($this->other, ['phone' => '+233200987654']);

        as_($this->admin)->postJson(MEMBERS, payload())->assertCreated();
    });

    it('accepts a phone whose earlier member was deleted', function (): void {
        newMember($this->church, ['phone' => '+233200987654'])->delete();

        as_($this->admin)->postJson(MEMBERS, payload())->assertCreated();
    });

    it('refuses another church\'s family, department or pastor', function (): void {
        $family = Family::factory()->create(['church_id' => $this->other->id]);
        $department = Department::factory()->create(['church_id' => $this->other->id]);
        $outsider = User::factory()->for($this->other)->create();

        as_($this->admin)->postJson(MEMBERS, payload([
            'family_id' => $family->id, 'family_role' => 'child',
            'department_ids' => [$department->id],
            'assigned_pastor_id' => $outsider->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['family_id', 'department_ids.0', 'assigned_pastor_id']);
    });

    it('ignores a member number or church sent by the client', function (): void {
        $response = as_($this->admin)->postJson(MEMBERS, payload(['member_number' => 'HACK-1', 'church_id' => $this->other->id, 'created_by' => 'x']))->assertCreated();

        $response->assertJsonPath('data.member_number', 'GRC-0001');
        expect(Member::query()->findOrFail($response->json('data.id'))->church_id)->toBe($this->church->id);
    });

    it('is refused until the church has a member number prefix', function (): void {
        $this->church->update(['member_number_prefix' => null]);

        as_($this->admin)->postJson(MEMBERS, payload())->assertUnprocessable();
        expect(Member::query()->count())->toBe(0);
    });
});

describe('updating', function (): void {
    it('changes only the fields sent', function (): void {
        $member = newMember($this->church, ['occupation' => 'Teacher', 'tribe' => 'Akan']);

        as_($this->admin)->putJson(MEMBERS.'/'.$member->id, ['occupation' => 'Nurse'])
            ->assertOk()->assertJsonPath('data.occupation', 'Nurse')->assertJsonPath('data.tribe', 'Akan');
    });

    it('logs a phone change with the old and new number and who made it', function (): void {
        $member = newMember($this->church, ['phone' => '+233200000001']);

        as_($this->admin)->putJson(MEMBERS.'/'.$member->id, ['phone' => '0200000002'])->assertOk();

        $log = AuditLog::query()->where('auditable_id', $member->id)->sole();
        expect($log->event)->toBe(AuditEvent::Updated)
            ->and($log->changed_fields)->toBe(['phone'])
            ->and($log->old_values)->toBe(['phone' => '+233200000001'])
            ->and($log->new_values)->toBe(['phone' => '+233200000002'])
            ->and($log->actor_id)->toBe($this->admin->id);
    });

    it('writes no log line when nothing changed', function (): void {
        $member = newMember($this->church, ['occupation' => 'Teacher']);

        as_($this->admin)->putJson(MEMBERS.'/'.$member->id, ['occupation' => 'Teacher'])->assertOk();

        expect(AuditLog::query()->count())->toBe(0);
    });

    it('lets a member keep their own phone number', function (): void {
        $member = newMember($this->church, ['phone' => '+233200000001']);

        as_($this->admin)->putJson(MEMBERS.'/'.$member->id, ['phone' => '+233200000001', 'occupation' => 'Nurse'])->assertOk();
    });

    it('never changes the member number or status', function (): void {
        $member = newMember($this->church, ['member_number' => 'GRC-0042']);

        as_($this->admin)->putJson(MEMBERS.'/'.$member->id, ['member_number' => 'GRC-9999', 'status' => 'archived', 'occupation' => 'Nurse'])->assertOk();

        expect($member->fresh()->member_number)->toBe('GRC-0042')->and($member->fresh()->status)->toBe(MemberStatus::Active);
    });

    it('refuses a family role for a member with no family', function (): void {
        $member = newMember($this->church);

        as_($this->admin)->putJson(MEMBERS.'/'.$member->id, ['family_role' => 'spouse'])
            ->assertUnprocessable()->assertJsonValidationErrors('family_id');
    });

    it('drops the family role when the family is removed', function (): void {
        $family = Family::factory()->create(['church_id' => $this->church->id]);
        $member = newMember($this->church, ['family_id' => $family->id, 'family_role' => FamilyRole::Child]);

        as_($this->admin)->putJson(MEMBERS.'/'.$member->id, ['family_id' => null])->assertOk();

        expect($member->fresh()->family_role)->toBeNull();
    });

    it('replaces departments and soft deletes the ones removed', function (): void {
        $choir = Department::factory()->create(['church_id' => $this->church->id]);
        $ushers = Department::factory()->create(['church_id' => $this->church->id]);
        $member = newMember($this->church);
        MemberDepartment::factory()->create(['church_id' => $this->church->id, 'member_id' => $member->id, 'department_id' => $choir->id]);

        as_($this->admin)->putJson(MEMBERS.'/'.$member->id, ['department_ids' => [$ushers->id]])
            ->assertOk()->assertJsonCount(1, 'data.departments')->assertJsonPath('data.departments.0.id', $ushers->id);

        expect(MemberDepartment::withTrashed()->where('member_id', $member->id)->count())->toBe(2)
            ->and($member->departments()->count())->toBe(1);
    });

    it('keeps a deceased member read-only', function (): void {
        $member = newMember($this->church, ['status' => MemberStatus::Deceased]);

        as_($this->admin)->putJson(MEMBERS.'/'.$member->id, ['occupation' => 'Nurse'])->assertForbidden();
    });

    it('is forbidden for roles that cannot edit members', function (string $role): void {
        $member = newMember($this->church);

        as_(staffWithRole($this->church, $role))->putJson(MEMBERS.'/'.$member->id, ['occupation' => 'Nurse'])->assertForbidden();
    })->with(['finance-secretary', 'department-head', 'youth-pastor']);

    it('fails validation', function (): void {
        $member = newMember($this->church);

        as_($this->admin)->putJson(MEMBERS.'/'.$member->id, ['first_name' => '', 'gender' => 'robot'])
            ->assertUnprocessable()->assertJsonValidationErrors(['first_name', 'gender']);
    });

    it('returns 404 for another church\'s member and leaves it untouched', function (): void {
        $foreign = newMember($this->other, ['occupation' => 'Teacher']);

        as_($this->admin)->putJson(MEMBERS.'/'.$foreign->id, ['occupation' => 'Hacked'])->assertNotFound();

        expect($foreign->fresh()->occupation)->toBe('Teacher');
    });
});

describe('changing status', function (): void {
    it('moves a member to inactive and keeps the reason in the change log', function (): void {
        $member = newMember($this->church);

        as_($this->admin)->patchJson(MEMBERS.'/'.$member->id.'/status', ['status' => 'inactive', 'note' => 'Relocated to Kumasi'])
            ->assertOk()->assertJsonPath('data.status', 'inactive')->assertJsonPath('data.status_note', 'Relocated to Kumasi');

        $log = AuditLog::query()->where('auditable_id', $member->id)->sole();
        expect($log->event)->toBe(AuditEvent::StatusChanged)
            ->and($log->old_values)->toBe(['status' => 'active'])
            ->and($log->new_values)->toBe(['status' => 'inactive'])
            ->and($log->reason)->toBe('Relocated to Kumasi');
    });

    it('lets the pastor confirm a prospect but not deactivate anyone', function (): void {
        $pastor = staffWithRole($this->church, 'pastor');
        $prospect = newMember($this->church, ['status' => MemberStatus::Prospect]);

        as_($pastor)->patchJson(MEMBERS.'/'.$prospect->id.'/status', ['status' => 'active'])->assertOk();
        as_($pastor)->patchJson(MEMBERS.'/'.$prospect->id.'/status', ['status' => 'inactive'])->assertForbidden();
        expect($prospect->fresh()->status)->toBe(MemberStatus::Active);
    });

    it('lets the admin reactivate', function (): void {
        $member = newMember($this->church, ['status' => MemberStatus::Inactive]);

        as_($this->admin)->patchJson(MEMBERS.'/'.$member->id.'/status', ['status' => 'active'])->assertOk();
    });

    it('is forbidden for read-only roles', function (): void {
        $member = newMember($this->church);

        as_(staffWithRole($this->church, 'finance-secretary'))->patchJson(MEMBERS.'/'.$member->id.'/status', ['status' => 'active'])->assertForbidden();
    });

    it('rejects an unknown status', function (): void {
        $member = newMember($this->church);

        as_($this->admin)->patchJson(MEMBERS.'/'.$member->id.'/status', ['status' => 'vanished'])->assertUnprocessable()->assertJsonValidationErrors('status');
        as_($this->admin)->patchJson(MEMBERS.'/'.$member->id.'/status', [])->assertUnprocessable();
    });

    it('returns 404 for another church\'s member', function (): void {
        $foreign = newMember($this->other);

        as_($this->admin)->patchJson(MEMBERS.'/'.$foreign->id.'/status', ['status' => 'inactive'])->assertNotFound();

        expect($foreign->fresh()->status)->toBe(MemberStatus::Active);
    });
});

describe('department membership', function (): void {
    it('ignores soft-deleted department links when reading a member\'s departments', function (): void {
        $choir = Department::factory()->create(['church_id' => $this->church->id]);
        $member = newMember($this->church);
        $link = MemberDepartment::factory()->create(['church_id' => $this->church->id, 'member_id' => $member->id, 'department_id' => $choir->id]);
        $link->delete();

        expect($member->departments()->count())->toBe(0)->and($choir->members()->count())->toBe(0);
    });
});
