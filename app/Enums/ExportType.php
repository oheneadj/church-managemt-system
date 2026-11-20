<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What an export holds.
 */
enum ExportType: string
{
    case Members = 'members';
    case Contributions = 'contributions';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Members => 'Member list',
            self::Contributions => 'Giving records',
        };
    }
}
