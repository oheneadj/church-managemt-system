<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ConsentPurpose;
use App\Models\Church;
use App\Models\Consent;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consent>
 */
class ConsentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'member_id' => fn (array $attributes) => Member::factory()->create(['church_id' => $attributes['church_id']])->getKey(),
            'purpose' => ConsentPurpose::Sms,
            'consented_at' => now(),
            'policy_version' => config('privacy.policy_version'),
        ];
    }

    /**
     * An agreement that was taken back.
     */
    public function withdrawn(): static
    {
        return $this->state(['withdrawn_at' => now()]);
    }
}
