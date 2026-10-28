<?php

declare(strict_types=1);

namespace App\DTOs\Attendance;

use Carbon\CarbonImmutable;

/**
 * A code to show as a QR code, and when it stops working.
 */
final readonly class QrCode
{
    /**
     * Holds the token and its expiry.
     */
    public function __construct(public string $token, public CarbonImmutable $expiresAt) {}
}
