<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\RecipientType;
use App\Models\Church;
use App\Models\MessageLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageLog>
 */
class MessageLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'channel' => MessageChannel::Sms,
            'recipient_type' => RecipientType::Member,
            'recipient_phone' => '+23324'.fake()->numerify('#######'),
            'message_type' => 'broadcast',
            'content' => fake()->sentence(),
            'status' => MessageStatus::Queued,
        ];
    }
}
