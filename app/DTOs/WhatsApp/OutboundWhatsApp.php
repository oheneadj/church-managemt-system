<?php

declare(strict_types=1);

namespace App\DTOs\WhatsApp;

use App\Enums\RecipientType;

/**
 * Everything the reply job needs to send one WhatsApp message and log it. `churchId` is null for a number that is
 * not on any church's list. A queued job has no request, so nothing here is read from one.
 */
final readonly class OutboundWhatsApp
{
    /**
     * Holds the message, who it is for and how to record it.
     */
    public function __construct(
        public ?string $churchId,
        public string $phone,
        public string $text,
        public string $messageType,
        public RecipientType $recipientType,
        public ?string $recipientId = null,
    ) {}
}
