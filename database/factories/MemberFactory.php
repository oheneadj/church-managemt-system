<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\MembershipType;
use App\Enums\MemberStatus;
use App\Models\Church;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'member_number' => 'MEM-'.fake()->unique()->numerify('####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => fake()->randomElement(Gender::cases()),
            'phone' => '+23320'.fake()->unique()->numerify('#######'),
            'membership_type' => MembershipType::Full,
            'status' => MemberStatus::Active,
            'whatsapp_opted_in' => false,
            'sms_opted_in' => true,
            'created_by' => fn (array $attributes) => User::factory()->create(['church_id' => $attributes['church_id']])->getKey(),
        ];
    }
}
