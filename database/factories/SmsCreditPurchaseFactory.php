<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MomoNetwork;
use App\Enums\SmsCreditPurchaseStatus;
use App\Models\Church;
use App\Models\SmsCreditPurchase;
use App\Models\SmsPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsCreditPurchase>
 */
class SmsCreditPurchaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'sms_package_id' => SmsPackage::factory(),
            'user_id' => fn (array $attributes): string => User::factory()->create(['church_id' => $attributes['church_id']])->id,
            'credits' => 100,
            'amount' => '10.00',
            'network' => MomoNetwork::Mtn,
            'phone' => '+233244123456',
            'status' => SmsCreditPurchaseStatus::Pending,
        ];
    }
}
