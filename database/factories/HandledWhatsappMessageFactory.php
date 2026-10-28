<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HandledWhatsappMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HandledWhatsappMessage>
 */
class HandledWhatsappMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['message_id' => 'wamid.'.fake()->unique()->bothify('????????????')];
    }
}
