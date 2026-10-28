<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Church;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'name' => fake()->name(),
            'phone' => '+23324'.fake()->unique()->numerify('#######'),
            'email' => fake()->unique()->safeEmail(),
            'is_active' => true,
        ];
    }
}
