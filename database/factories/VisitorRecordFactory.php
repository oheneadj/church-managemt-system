<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FollowUpStatus;
use App\Models\Church;
use App\Models\VisitorRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisitorRecord>
 */
class VisitorRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'name' => fake()->name(),
            'phone' => '+23327'.fake()->numerify('#######'),
            'first_visit_date' => fake()->date(),
            'visit_count' => 1,
            'follow_up_status' => FollowUpStatus::Pending,
        ];
    }
}
