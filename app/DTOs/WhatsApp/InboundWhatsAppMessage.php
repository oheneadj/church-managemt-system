<?php

declare(strict_types=1);

namespace App\DTOs\WhatsApp;

use App\ValueObjects\PhoneNumber;

/**
 * One text message a member sent to the church's WhatsApp number: Meta's id for it, who sent it and what it said.
 */
final readonly class InboundWhatsAppMessage
{
    /**
     * Holds the message as the webhook handler needs it.
     */
    public function __construct(public string $id, public PhoneNumber $phone, public string $text) {}
}
