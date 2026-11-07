<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlatformAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformAdmin>
 */
class PlatformAdminFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'a long platform passphrase',
            'is_active' => true,
        ];
    }
}
