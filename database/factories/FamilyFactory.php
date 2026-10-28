<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Church;
use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Family>
 */
class FamilyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'name' => fake()->lastName().' Family',
        ];
    }
}
