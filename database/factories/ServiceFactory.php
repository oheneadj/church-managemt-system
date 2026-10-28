<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ServiceType;
use App\Models\Church;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'name' => 'Sunday Morning Service',
            'type' => ServiceType::Sunday,
            'checkin_code' => strtoupper(fake()->unique()->bothify('??##')),
            'service_date' => fake()->date(),
            'is_open' => false,
            'created_by' => fn (array $attributes) => User::factory()->create(['church_id' => $attributes['church_id']])->getKey(),
        ];
    }

    public function open(): static
    {
        return $this->state(['is_open' => true]);
    }
}
