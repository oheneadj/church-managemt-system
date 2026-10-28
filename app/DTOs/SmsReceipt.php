<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * What a provider answered when it accepted a text message: its own reference for the message and a short
 * record of the reply that is safe to keep (no phone numbers and no message text).
 */
final readonly class SmsReceipt
{
    /**
     * Holds the provider's reference (null when the provider gives none) and the safe parts of its reply.
     *
     * @param  array<string, mixed>  $response
     */
    public function __construct(public ?string $reference = null, public array $response = []) {}
}
