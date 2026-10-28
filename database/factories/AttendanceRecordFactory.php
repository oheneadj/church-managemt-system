<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CheckInMethod;
use App\Models\AttendanceRecord;
use App\Models\Church;
use App\Models\Member;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'service_id' => fn (array $attributes) => Service::factory()->create(['church_id' => $attributes['church_id']])->getKey(),
            'member_id' => fn (array $attributes) => Member::factory()->create(['church_id' => $attributes['church_id']])->getKey(),
            'method' => CheckInMethod::Manual,
            'checked_in_at' => now(),
        ];
    }
}
