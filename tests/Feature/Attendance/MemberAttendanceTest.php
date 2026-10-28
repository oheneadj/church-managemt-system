<?php

declare(strict_types=1);

use App\Models\AttendanceRecord;
use App\Models\Church;
use App\Models\Member;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

// One member's attendance over the last few weeks (docs/11 story 3.7, docs/02 "per-member attendance rate").

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->admin = User::factory()->for($this->church)->create();
    $this->admin->assignRole('church-admin');
    Sanctum::actingAs($this->admin, ['*']);
    $this->member = Member::factory()->create(['church_id' => $this->church->id]);
    $this->url = 'http://grace.localhost/api/v1/members/'.$this->member->id.'/attendance';
    $this->travelTo('2026-10-26 12:00:00');
});

/** Holds a service on the date, with someone checked in so it counts as held. */
function heldService(Church $church, string $date): Service
{
    $service = Service::factory()->create(['church_id' => $church->id, 'service_date' => $date]);
    AttendanceRecord::factory()->create(['church_id' => $church->id, 'service_id' => $service->id]);

    return $service;
}

it('gives the services held, how many the member was at, the rate and where', function (): void {
    $first = heldService($this->church, '2026-10-04');
    heldService($this->church, '2026-10-11');
    $third = heldService($this->church, '2026-10-18');
    foreach ([$first, $third] as $service) {
        AttendanceRecord::factory()->create(['church_id' => $this->church->id, 'service_id' => $service->id, 'member_id' => $this->member->id, 'checked_in_at' => $service->service_date->setTime(9, 0)]);
    }

    $response = $this->getJson($this->url.'?weeks=4')->assertOk();

    expect($response->json('services_held'))->toBe(3)
        ->and($response->json('attended'))->toBe(2)
        ->and($response->json('attendance_rate'))->toBe(67)
        ->and($response->json('weeks'))->toBe(4)
        ->and(array_column($response->json('records'), 'service_id'))->toBe([$first->id, $third->id])
        ->and($response->json('records.0.service_date'))->toBe('2026-10-04');
});

it('looks back 12 weeks when no number is given', function (): void {
    heldService($this->church, '2026-08-09');
    heldService($this->church, '2026-10-18');

    $this->getJson($this->url)->assertOk()->assertJsonPath('weeks', 12)->assertJsonPath('services_held', 2);
});

it('does not count services older than the window', function (): void {
    heldService($this->church, '2026-08-02');

    $this->getJson($this->url.'?weeks=4')->assertOk()->assertJsonPath('services_held', 0)->assertJsonPath('attendance_rate', 0);
});

it('does not count a service nobody came to', function (): void {
    Service::factory()->create(['church_id' => $this->church->id, 'service_date' => '2026-10-18']);

    $this->getJson($this->url.'?weeks=4')->assertOk()->assertJsonPath('services_held', 0);
});

it('rejects a number of weeks outside the range', function (string $query): void {
    $this->getJson($this->url.$query)->assertUnprocessable()->assertJsonValidationErrors('weeks');
})->with(['?weeks=0', '?weeks=53', '?weeks=abc']);

it('is a 404 for another church\'s member, and needs a token', function (): void {
    $stranger = Member::factory()->create();
    $this->getJson('http://grace.localhost/api/v1/members/'.$stranger->id.'/attendance')->assertNotFound();

    $this->app['auth']->forgetGuards();
    $this->getJson($this->url)->assertUnauthorized();
});

it('is limited to members the viewer may see', function (): void {
    $head = User::factory()->for($this->church)->create();
    $head->assignRole('department-head');
    Sanctum::actingAs($head, ['*']);

    $this->getJson($this->url)->assertForbidden();
});
