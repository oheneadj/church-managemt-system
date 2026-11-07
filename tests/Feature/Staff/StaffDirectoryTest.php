<?php

declare(strict_types=1);

use App\Models\Church;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

// The staff directory (D-055, docs/08 Staff): a read-only list of the church's active staff, just their ids and names, so
// a screen that edits departments can offer them as a department's head. It needs `members.edit` (church admin and pastor),
// not `staff.manage`, because the pastor edits departments but may not see staff emails or phone numbers.

const STAFF_DIRECTORY = 'http://grace.localhost/api/v1/staff/directory';

/** A staff member of the church with one role. */
function directoryStaff(Church $church, string $role, array $attributes = []): User
{
    $user = User::factory()->for($church)->create($attributes);
    $user->assignRole($role);

    return $user;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->other = Church::factory()->create(['slug' => 'other']);
    $this->admin = directoryStaff($this->church, 'church-admin', ['name' => 'Kofi Admin']);
});

it('is refused without a token', function (): void {
    $this->getJson(STAFF_DIRECTORY)->assertUnauthorized();
});

it('is refused to roles that may not edit members', function (string $role): void {
    Sanctum::actingAs(directoryStaff($this->church, $role), ['*']);

    $this->getJson(STAFF_DIRECTORY)->assertForbidden();
})->with(['finance-secretary', 'department-head', 'youth-pastor']);

it('is open to the church admin and the pastor, who edit members', function (string $role): void {
    Sanctum::actingAs(directoryStaff($this->church, $role), ['*']);

    $this->getJson(STAFF_DIRECTORY)->assertOk();
})->with(['church-admin', 'pastor']);

describe('the list', function (): void {
    it('lists the active staff by name with only their id and name, never an email, phone or password', function (): void {
        $pastor = directoryStaff($this->church, 'pastor', ['name' => 'Abena Pastor']);
        directoryStaff($this->church, 'finance-secretary', ['name' => 'Zeta Finance', 'is_active' => false]);
        Sanctum::actingAs($pastor, ['*']);

        $response = $this->getJson(STAFF_DIRECTORY)->assertOk()->assertJsonStructure(['data', 'meta' => ['total'], 'links']);

        expect($response->json('data'))->toBe([
            ['id' => $pastor->id, 'name' => 'Abena Pastor'],
            ['id' => $this->admin->id, 'name' => 'Kofi Admin'],
        ])->and($response->getContent())->not->toContain('@')->not->toContain('password')->not->toContain('Zeta Finance');
    });

    it('is paged, and refuses a page size outside the allowed range', function (): void {
        directoryStaff($this->church, 'pastor', ['name' => 'Abena Pastor']);
        Sanctum::actingAs($this->admin, ['*']);

        $this->getJson(STAFF_DIRECTORY.'?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2);
        $this->getJson(STAFF_DIRECTORY.'?per_page=0')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson(STAFF_DIRECTORY.'?per_page='.(config('limits.per_page_max') + 1))->assertUnprocessable()->assertJsonValidationErrors('per_page');
    });

    it('never lists another church\'s staff (isolation)', function (): void {
        directoryStaff($this->other, 'church-admin', ['name' => 'Someone Else']);
        Sanctum::actingAs($this->admin, ['*']);

        $response = $this->getJson(STAFF_DIRECTORY)->assertOk();

        expect($response->json('meta.total'))->toBe(1)->and($response->getContent())->not->toContain('Someone Else');
    });
});
