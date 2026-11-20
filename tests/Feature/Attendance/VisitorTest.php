<?php

declare(strict_types=1);

use App\Enums\FollowUpStatus;
use App\Models\AttendanceRecord;
use App\Models\Church;
use App\Models\Service;
use App\Models\User;
use App\Models\VisitorRecord;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

// First-time visitor capture (docs/11 story 3.6, docs/02 Module 3). A visitor is a record apart from a member.

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->admin = User::factory()->for($this->church)->create();
    $this->admin->assignRole('church-admin');
    $this->service = Service::factory()->open()->create(['church_id' => $this->church->id, 'service_date' => '2026-10-25']);
    $this->url = 'http://grace.localhost/api/v1/services/'.$this->service->id.'/visitors';
    Sanctum::actingAs($this->admin, ['*']);
});

it('records a new visitor and counts them at the service', function (): void {
    $response = $this->postJson($this->url, ['name' => 'Yaw Boateng', 'phone' => '0244999000', 'how_heard' => 'A friend'])->assertCreated();

    $response->assertJsonPath('data.name', 'Yaw Boateng')
        ->assertJsonPath('data.phone', '+233244999000')
        ->assertJsonPath('data.how_heard', 'A friend')
        ->assertJsonPath('data.visit_count', 1)
        ->assertJsonPath('data.follow_up_status', 'pending')
        ->assertJsonPath('data.first_visit_date', '2026-10-25');
    $record = AttendanceRecord::query()->sole();
    expect($record->visitor_name)->toBe('Yaw Boateng')->and($record->member_id)->toBeNull()->and($record->service_id)->toBe($this->service->id);
});

it('needs only a name', function (): void {
    $this->postJson($this->url, ['name' => 'Walk-in'])->assertCreated()->assertJsonPath('data.phone', null);
});

it('matches a returning visitor by phone and counts a return visit at another service', function (): void {
    $this->postJson($this->url, ['name' => 'Yaw Boateng', 'phone' => '0244999000'])->assertCreated();
    $next = Service::factory()->open()->create(['church_id' => $this->church->id, 'service_date' => '2026-11-01']);

    $response = $this->postJson('http://grace.localhost/api/v1/services/'.$next->id.'/visitors', ['name' => 'Yaw B', 'phone' => '+233244999000'])->assertCreated();

    expect(VisitorRecord::query()->count())->toBe(1)
        ->and($response->json('data.visit_count'))->toBe(2)
        ->and($response->json('data.last_visit_date'))->toBe('2026-11-01')
        ->and(AttendanceRecord::query()->count())->toBe(2);
});

it('counts the visit once when the form is sent twice for one service', function (): void {
    $this->postJson($this->url, ['name' => 'Yaw Boateng', 'phone' => '0244999000'])->assertCreated();
    $second = $this->postJson($this->url, ['name' => 'Yaw Boateng', 'phone' => '0244999000'])->assertCreated();

    expect($second->json('data.visit_count'))->toBe(1)->and(AttendanceRecord::query()->count())->toBe(1)->and(VisitorRecord::query()->count())->toBe(1);
});

it('treats two visitors without a phone as two people', function (): void {
    $this->postJson($this->url, ['name' => 'Walk-in'])->assertCreated();
    $this->postJson($this->url, ['name' => 'Walk-in'])->assertCreated();

    expect(VisitorRecord::query()->count())->toBe(2)->and(AttendanceRecord::query()->count())->toBe(2);
});

it('does not match a visitor of another church by phone', function (): void {
    VisitorRecord::factory()->create(['phone' => '+233244999000', 'name' => 'Theirs']);

    $this->postJson($this->url, ['name' => 'Yaw Boateng', 'phone' => '0244999000'])->assertCreated()->assertJsonPath('data.name', 'Yaw Boateng');

    expect(VisitorRecord::query()->withoutGlobalScopes()->count())->toBe(2)
        ->and(VisitorRecord::query()->withoutGlobalScopes()->where('name', 'Theirs')->sole()->visit_count)->toBe(1);
});

it('rejects bad input', function (array $body, string $field): void {
    $this->postJson($this->url, $body)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'no name' => [[], 'name'],
    'bad phone' => [['name' => 'X', 'phone' => 'abc'], 'phone'],
    'long how heard' => [['name' => 'X', 'how_heard' => str_repeat('a', 300)], 'how_heard'],
]);

it('needs a token and members.edit, and the service must be this church\'s', function (): void {
    $other = Church::factory()->create();
    $theirs = Service::factory()->create(['church_id' => $other->id]);
    $this->postJson('http://grace.localhost/api/v1/services/'.$theirs->id.'/visitors', ['name' => 'X'])->assertNotFound();

    foreach (['finance-secretary', 'department-head', 'youth-pastor'] as $role) {
        $staff = User::factory()->for($this->church)->create();
        $staff->assignRole($role);
        Sanctum::actingAs($staff, ['*']);
        $this->postJson($this->url, ['name' => 'X'])->assertForbidden();
    }

    $this->app['auth']->forgetGuards();
    $this->postJson($this->url, ['name' => 'X'])->assertUnauthorized();
    expect(VisitorRecord::query()->count())->toBe(0);
});

it('starts a visitor as pending follow-up', function (): void {
    $this->postJson($this->url, ['name' => 'Yaw'])->assertCreated();

    expect(VisitorRecord::query()->sole()->follow_up_status)->toBe(FollowUpStatus::Pending);
});
