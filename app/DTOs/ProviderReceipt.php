<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * What a provider answered when it accepted a message of any channel (text, WhatsApp, email): its own reference for the
 * message and a short record of the reply that is safe to keep (no phone numbers, addresses or message text).
 */
final readonly class ProviderReceipt
{
    /**
     * Holds the provider's reference (null when the provider gives none) and the safe parts of its reply.
     *
     * @param  array<string, mixed>  $response
     */
    public function __construct(public ?string $reference = null, public array $response = []) {}
}
