<?php

declare(strict_types=1);

use App\Models\Church;
use App\Models\PlatformAdmin;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Spatie\Permission\Models\Role;

// The seeder runs on every web container start (deploy/bin/start). Roles and permissions are seeded everywhere; a demo church,
// its admin and a platform admin only in local development, and on a staging (test) site only when the sign-in is given
// explicitly, because a guessable default password on a public test site would be a hole and the sign-in code is emailed, so
// the address has to be a real inbox. Production never gets demo data.

/** Runs the seeder as if the app were in this environment, with these demo settings. */
function seedAs(string $environment, array $demo = []): void
{
    app()['env'] = $environment;
    config(['demo.admin_email' => null, 'demo.admin_password' => null, 'demo.platform_admin_email' => null, 'demo.platform_admin_password' => null, ...$demo]);

    app(DatabaseSeeder::class)->run();
}

describe('local development', function (): void {
    it('creates the demo church, its admin and a platform admin', function (): void {
        seedAs('local', [
            'demo.admin_email' => 'admin@demo.church.test', 'demo.admin_password' => 'a local passphrase',
            'demo.platform_admin_email' => 'owner@platform.church.test', 'demo.platform_admin_password' => 'a local platform passphrase',
        ]);

        $church = Church::query()->where('slug', 'demo')->sole();
        expect(User::query()->withoutGlobalScopes()->where('church_id', $church->id)->sole()->email)->toBe('admin@demo.church.test')
            ->and(PlatformAdmin::query()->sole()->email)->toBe('owner@platform.church.test');
    });
});

describe('a staging (test) site', function (): void {
    it('creates the demo church and a platform admin from the sign-in it is given', function (): void {
        seedAs('staging', [
            'demo.admin_email' => 'me@example.com', 'demo.admin_password' => 'a staging admin passphrase',
            'demo.platform_admin_email' => 'owner@example.com', 'demo.platform_admin_password' => 'a staging platform passphrase',
        ]);

        $church = Church::query()->where('slug', 'demo')->sole();
        $admin = User::query()->withoutGlobalScopes()->where('church_id', $church->id)->sole();
        expect($admin->email)->toBe('me@example.com')->and($admin->hasRole('church-admin'))->toBeTrue()
            ->and(PlatformAdmin::query()->sole()->email)->toBe('owner@example.com');
    });

    it('is safe to run on every deploy, and does not make a second church or admin', function (): void {
        $demo = ['demo.admin_email' => 'me@example.com', 'demo.admin_password' => 'a staging admin passphrase'];

        seedAs('staging', $demo);
        seedAs('staging', $demo);

        expect(Church::query()->count())->toBe(1)->and(User::query()->withoutGlobalScopes()->count())->toBe(1);
    });

    it('creates no demo church when the admin sign-in is not given, but still seeds roles', function (array $demo): void {
        seedAs('staging', $demo);

        expect(Church::query()->count())->toBe(0)->and(User::query()->withoutGlobalScopes()->count())->toBe(0)
            ->and(Role::query()->count())->toBeGreaterThan(0);
    })->with([
        'nothing' => [[]],
        'no password' => [['demo.admin_email' => 'me@example.com']],
        'no email' => [['demo.admin_password' => 'a staging admin passphrase']],
    ]);

    it('creates no platform admin unless both its email and password are given', function (array $demo): void {
        seedAs('staging', $demo);

        expect(PlatformAdmin::query()->count())->toBe(0);
    })->with([
        'no password' => [['demo.platform_admin_email' => 'owner@example.com']],
        'no email' => [['demo.platform_admin_password' => 'a staging platform passphrase']],
    ]);
});

describe('production', function (): void {
    it('never creates demo data, even when the settings are present', function (): void {
        seedAs('production', [
            'demo.admin_email' => 'me@example.com', 'demo.admin_password' => 'a passphrase here',
            'demo.platform_admin_email' => 'owner@example.com', 'demo.platform_admin_password' => 'a platform passphrase',
        ]);

        expect(Church::query()->count())->toBe(0)->and(PlatformAdmin::query()->count())->toBe(0)
            ->and(Role::query()->count())->toBeGreaterThan(0);
    });
});
