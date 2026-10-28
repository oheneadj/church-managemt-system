<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MilestoneType;
use App\Models\Church;
use App\Models\Member;
use App\Models\MemberMilestone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberMilestone>
 */
class MemberMilestoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'member_id' => fn (array $attributes) => Member::factory()->create(['church_id' => $attributes['church_id']])->getKey(),
            'type' => MilestoneType::Baptism,
            'occurred_at' => fake()->date(),
            'created_by' => fn (array $attributes) => User::factory()->create(['church_id' => $attributes['church_id']])->getKey(),
        ];
    }
}
