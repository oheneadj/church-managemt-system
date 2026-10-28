<?php

declare(strict_types=1);

use App\Models\Church;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

it('does not register a dev-only provider in bootstrap/providers.php', function (): void {
    // Telescope is a --dev package; the production image is built with `composer install --no-dev`.
    // A provider that extends it in this list would crash the app on boot there.
    foreach (require base_path('bootstrap/providers.php') as $provider) {
        expect(is_subclass_of($provider, TelescopeApplicationServiceProvider::class))->toBeFalse("{$provider} needs a dev-only package");
    }
});

it('excludes Telescope from package auto-discovery', function (): void {
    $extra = json_decode((string) file_get_contents(base_path('composer.json')), true)['extra'];

    expect($extra['laravel']['dont-discover'])->toContain('laravel/telescope');
});

it('keeps the roles seeder safe to run on every deploy', function (): void {
    $this->seed(DatabaseSeeder::class);
    $rolesAfterFirst = Role::query()->count();
    $permissionsAfterFirst = Permission::query()->count();

    $this->seed(DatabaseSeeder::class);

    expect(Role::query()->count())->toBe($rolesAfterFirst)->toBe(5)
        ->and(Permission::query()->count())->toBe($permissionsAfterFirst)
        ->and(Role::findByName('church-admin')->permissions()->count())->toBe($permissionsAfterFirst);
});

it('never seeds the demo church outside local development', function (): void {
    $this->seed(DatabaseSeeder::class);

    expect(Church::query()->count())->toBe(0);
});
