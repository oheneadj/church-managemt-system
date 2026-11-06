<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BroadcastStatus;
use App\Enums\MessageChannel;
use App\Models\Broadcast;
use App\Models\Church;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Broadcast>
 */
class BroadcastFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'sent_by' => fn (array $attributes) => User::factory()->create(['church_id' => $attributes['church_id']])->getKey(),
            'channel' => MessageChannel::Sms,
            'message' => 'Service is at 9am this Sunday.',
            'filters' => [],
            'parts_per_message' => 1,
            'status' => BroadcastStatus::Queued,
        ];
    }
}
