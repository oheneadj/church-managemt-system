<?php

declare(strict_types=1);

namespace App\DTOs\WhatsApp;

/**
 * A member's CHECKIN message: the service code they added, or null when they sent the word alone.
 */
final readonly class CheckinCommand
{
    /**
     * Holds the optional service code, in upper case.
     */
    public function __construct(public ?string $serviceCode = null) {}
}
