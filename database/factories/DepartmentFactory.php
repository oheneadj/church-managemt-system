<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Church;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'name' => fake()->unique()->word().' Ministry',
            'is_active' => true,
        ];
    }
}
