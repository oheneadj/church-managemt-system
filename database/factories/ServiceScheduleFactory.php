<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ServiceType;
use App\Enums\Weekday;
use App\Models\Church;
use App\Models\ServiceSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceSchedule>
 */
class ServiceScheduleFactory extends Factory
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
            'weekday' => Weekday::Sunday,
            'starts_at' => '09:00:00',
            'ends_at' => '11:30:00',
            'is_active' => true,
            'created_by' => fn (array $attributes) => User::factory()->create(['church_id' => $attributes['church_id']])->getKey(),
        ];
    }

    /**
     * A schedule that is switched off.
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
