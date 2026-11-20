<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StaffRole;
use App\Models\Church;
use App\Models\FundType;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Services\DefaultFundTypes;
use Illuminate\Database\Seeder;

/**
 * Seeds roles and permissions everywhere. Demo data is made only in local development and on a staging (test) site, never in
 * production. In local development the demo sign-ins have defaults (config/demo.php); on staging there are none, so the demo
 * church and the platform admin are made only when their email and password are set in the environment.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        if (! app()->environment(['local', 'staging'])) {
            return;
        }

        $this->seedDemoChurch();
        $this->seedPlatformAdmin();
    }

    /**
     * The demo church and its church admin, when the admin's email and password are set. It is safe to run on every deploy:
     * it finds what it made before and only refreshes the admin's sign-in.
     */
    private function seedDemoChurch(): void
    {
        if (blank(config('demo.admin_email')) || blank(config('demo.admin_password'))) {
            return;
        }

        $church = Church::query()->firstOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Demo Assembly', 'timezone' => 'Africa/Accra', 'member_number_prefix' => 'DEM'],
        );

        $admin = User::query()->withoutGlobalScopes()->firstOrCreate(
            ['church_id' => $church->id, 'phone' => '+233200000000'],
            ['name' => 'Demo Admin', 'email' => config('demo.admin_email')],
        );
        // Signs in with an email and password like every staff member (D-054). The password is not mass assignable.
        $admin->forceFill([
            'email' => config('demo.admin_email'),
            'password' => config('demo.admin_password'),
            'email_verified_at' => now(),
        ])->save();
        $admin->assignRole(StaffRole::ChurchAdmin);

        if (! FundType::query()->withoutGlobalScopes()->where('church_id', $church->id)->exists()) {
            app(DefaultFundTypes::class)->createFor($church);
        }
    }

    /**
     * A platform admin (D-069), when its email and password are set.
     */
    private function seedPlatformAdmin(): void
    {
        if (blank(config('demo.platform_admin_email')) || blank(config('demo.platform_admin_password'))) {
            return;
        }

        PlatformAdmin::query()->updateOrCreate(
            ['email' => config('demo.platform_admin_email')],
            ['name' => 'Demo Platform Owner', 'password' => config('demo.platform_admin_password'), 'is_active' => true],
        );
    }
}
