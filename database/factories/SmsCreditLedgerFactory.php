<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CreditLedgerType;
use App\Models\Church;
use App\Models\SmsCreditLedger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsCreditLedger>
 */
class SmsCreditLedgerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'type' => CreditLedgerType::Allocation,
            'credits' => 100,
            'balance_after' => 100,
        ];
    }
}
