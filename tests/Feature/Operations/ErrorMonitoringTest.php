<?php

declare(strict_types=1);

use App\Models\Church;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\State\Scope;

use function Sentry\configureScope;

// Agreement section 27: errors reach an error monitor, with the staff id and church id but never a name, phone or email.

afterEach(fn () => SentrySdk::setCurrentHub(new Hub));

it('tells the monitor the staff id, church id and role, and nothing personal', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    SentrySdk::setCurrentHub(new Hub(ClientBuilder::create(['dsn' => 'https://key@o0.ingest.sentry.io/1'])->getClient()));
    $church = Church::factory()->create(['slug' => 'grace']);
    $admin = User::factory()->for($church)->create(['name' => 'Akosua Mensah', 'phone' => '+233244123456']);
    $admin->assignRole('church-admin');
    Sanctum::actingAs($admin, ['*']);

    $this->getJson('http://grace.localhost/api/v1/auth/me')->assertOk();

    $captured = null;
    configureScope(function (Scope $scope) use (&$captured): void {
        $captured = $scope;
    });
    $event = $captured->applyToEvent(Event::createEvent());

    expect($event->getUser()->getId())->toBe($admin->id)
        ->and($event->getTags())->toMatchArray(['church_id' => $church->id, 'role' => 'church-admin'])
        ->and(json_encode([$event->getUser(), $event->getTags()]))->not->toContain('Akosua')->not->toContain('+233244123456');
});

it('does not send request bodies or personal data by default', function (): void {
    expect(config('sentry.send_default_pii'))->toBeFalse()
        ->and(config('sentry.max_request_body_size'))->toBe('none')
        ->and(config('sentry.breadcrumbs.sql_bindings'))->toBeFalse();
});
