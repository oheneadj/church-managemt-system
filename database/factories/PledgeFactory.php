<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PledgeStatus;
use App\Models\Church;
use App\Models\FundType;
use App\Models\Member;
use App\Models\Pledge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pledge>
 */
class PledgeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'member_id' => fn (array $attributes) => Member::factory()->create(['church_id' => $attributes['church_id']])->getKey(),
            'fund_type_id' => fn (array $attributes) => FundType::factory()->create(['church_id' => $attributes['church_id']])->getKey(),
            'description' => 'Building Fund Pledge',
            'amount_pledged' => '1000.00',
            'amount_paid' => '0.00',
            'target_date' => fake()->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
            'status' => PledgeStatus::Active,
            'created_by' => fn (array $attributes) => User::factory()->create(['church_id' => $attributes['church_id']])->getKey(),
        ];
    }
}
