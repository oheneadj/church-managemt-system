<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The allowed message channel values, stored as a plain string column (D-017).
 */
enum MessageChannel: string
{
    case Sms = 'sms';
    case Whatsapp = 'whatsapp';
    case Email = 'email';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Sms => 'SMS',
            self::Whatsapp => 'WhatsApp',
            self::Email => 'Email',
        };
    }
}
