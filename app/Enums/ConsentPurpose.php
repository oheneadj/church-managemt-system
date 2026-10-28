<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a member agreed to receive, stored as a plain string column (D-017). Each purpose is consented to separately.
 */
enum ConsentPurpose: string
{
    case Sms = 'sms';
    case Whatsapp = 'whatsapp';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Sms => 'Text messages',
            self::Whatsapp => 'WhatsApp messages',
        };
    }
}
