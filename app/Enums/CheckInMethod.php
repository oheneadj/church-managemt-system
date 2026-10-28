<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The allowed check in method values, stored as a plain string column (D-017).
 */
enum CheckInMethod: string
{
    case Manual = 'manual';
    case Whatsapp = 'whatsapp';
    case Qr = 'qr';
    case Biometric = 'biometric';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Whatsapp => 'WhatsApp',
            self::Qr => 'QR code',
            self::Biometric => 'Biometric',
        };
    }
}
