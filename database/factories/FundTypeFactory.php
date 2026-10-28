<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Church;
use App\Models\FundType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FundType>
 */
class FundTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'name' => fake()->unique()->word().' Fund',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
