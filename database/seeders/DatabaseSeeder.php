<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Church;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds roles everywhere, plus a demo church and admin for local development only.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        if (! app()->environment('local')) {
            return;
        }

        $church = Church::query()->firstOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Demo Assembly', 'timezone' => 'Africa/Accra'],
        );

        $admin = User::query()->withoutGlobalScopes()->firstOrCreate(
            ['church_id' => $church->id, 'phone' => '+233200000000'],
            ['name' => 'Demo Admin'],
        );
        $admin->assignRole('church-admin');
    }
}
