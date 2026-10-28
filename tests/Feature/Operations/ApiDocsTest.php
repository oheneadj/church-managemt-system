<?php

declare(strict_types=1);

use App\Models\Church;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

// Agreement section 35: the OpenAPI spec is committed, matches the code, covers every endpoint,
// and the interactive docs are not public outside local development.

const DOCS = 'http://grace.localhost/docs/api';

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace']);
});

it('serves the interactive docs and the JSON spec to staff who may change church settings', function (): void {
    $admin = User::factory()->for($this->church)->create();
    $admin->assignRole('church-admin');

    $this->actingAs($admin)->get(DOCS)->assertOk();
    $this->actingAs($admin)->getJson(DOCS.'.json')->assertOk()->assertJsonPath('info.title', 'Church Management API');
});

it('refuses the docs to a visitor who is not signed in', function (): void {
    $this->get(DOCS)->assertForbidden();
    $this->getJson(DOCS.'.json')->assertForbidden();
});

it('refuses the docs to staff without the settings permission', function (): void {
    $pastor = User::factory()->for($this->church)->create();
    $pastor->assignRole('pastor');

    $this->actingAs($pastor)->get(DOCS)->assertForbidden();
});

it('has a committed spec that matches what the code produces', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'openapi');

    expect(Artisan::call('scramble:export', ['--path' => $path, '--fail-on-unknown' => true]))->toBe(0);
    expect(json_decode((string) file_get_contents($path), true))
        ->toBe(json_decode((string) file_get_contents(base_path('docs/openapi.json')), true));
});

it('documents every API endpoint', function (): void {
    $spec = json_decode((string) file_get_contents(base_path('docs/openapi.json')), true);
    $documented = [];
    foreach ($spec['paths'] as $path => $operations) {
        foreach (array_keys($operations) as $method) {
            $documented[] = strtoupper($method).' '.$path;
        }
    }

    $actual = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/'))
        ->flatMap(fn ($route) => collect($route->methods())->reject(fn ($m) => in_array($m, ['HEAD', 'OPTIONS'], true))
            ->map(fn ($m) => $m.' /'.preg_replace('#^api/#', '', $route->uri())))
        ->all();

    expect($actual)->not->toBeEmpty();
    foreach ($actual as $endpoint) {
        expect($documented)->toContain($endpoint);
    }
});

it('says which endpoints need a token and which are public', function (): void {
    $spec = json_decode((string) file_get_contents(base_path('docs/openapi.json')), true);

    expect($spec['paths']['/v1/members']['get'])->not->toHaveKey('security')
        ->and($spec['security'])->toBe([['http' => []]])
        ->and($spec['paths']['/v1/auth/otp/request']['post']['security'])->toBe([])
        ->and($spec['paths']['/health']['get']['security'])->toBe([]);
});
