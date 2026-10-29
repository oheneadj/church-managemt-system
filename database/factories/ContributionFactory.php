<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\FundType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contribution>
 */
class ContributionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'fund_type_id' => fn (array $attributes) => FundType::factory()->create(['church_id' => $attributes['church_id']])->getKey(),
            'fund_name' => fn (array $attributes) => FundType::query()->withoutGlobalScopes()->whereKey($attributes['fund_type_id'])->value('name') ?? 'Tithe',
            'amount' => '200.00',
            'payment_method' => PaymentMethod::Cash,
            'is_voided' => false,
            'recorded_by' => fn (array $attributes) => User::factory()->create(['church_id' => $attributes['church_id']])->getKey(),
            'contributed_at' => now(),
        ];
    }

    public function voided(string $reason = 'Entered in error'): static
    {
        return $this->state(fn (array $attributes) => [
            'is_voided' => true,
            'voided_at' => now(),
            'void_reason' => $reason,
            'voided_by' => $attributes['recorded_by'],
        ]);
    }
}
