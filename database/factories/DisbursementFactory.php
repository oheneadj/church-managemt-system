<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DisbursementStatus;
use App\Enums\PaymentProviderName;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\Disbursement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Disbursement>
 */
class DisbursementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'contribution_id' => fn (array $attributes) => Contribution::factory()->create(['church_id' => $attributes['church_id']])->getKey(),
            'gross_amount' => '100.00',
            'platform_fee' => '1.00',
            'requested_amount' => '99.00',
            'status' => DisbursementStatus::Pending,
            'provider' => PaymentProviderName::Payperlez,
            'attempts' => 0,
        ];
    }
}
