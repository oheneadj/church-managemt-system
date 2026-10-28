<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChurchPlan;
use App\Models\Church;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Church>
 */
class ChurchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Assembly',
            'slug' => fake()->unique()->slug(2),
            'plan' => ChurchPlan::Starter,
            'timezone' => 'Africa/Accra',
            'member_number_prefix' => strtoupper(fake()->unique()->lexify('???')),
            'sms_credits' => 0,
            'is_active' => true,
        ];
    }

    public function suspended(): static
    {
        return $this->state(['is_active' => false]);
    }
}
