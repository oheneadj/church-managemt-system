<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WebhookSource;
use App\Models\WebhookLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookLog>
 */
class WebhookLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => WebhookSource::Payperlez,
            'event' => 'collection.success',
            'payload' => json_encode(['reference' => fake()->uuid()], JSON_THROW_ON_ERROR),
            'processed' => false,
        ];
    }
}
