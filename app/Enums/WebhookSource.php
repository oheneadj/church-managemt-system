<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The allowed webhook source values, stored as a plain string column (D-017).
 */
enum WebhookSource: string
{
    case Paystack = 'paystack';
    case Payperlez = 'payperlez';
    case MetaWhatsapp = 'meta_whatsapp';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Paystack => 'Paystack',
            self::Payperlez => 'PayPerlez',
            self::MetaWhatsapp => 'Meta WhatsApp',
        };
    }
}
