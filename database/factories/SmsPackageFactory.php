<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SmsPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsPackage>
 */
class SmsPackageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'credits' => 100,
            'price' => '10.00',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
