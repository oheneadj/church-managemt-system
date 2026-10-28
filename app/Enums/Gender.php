<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The allowed gender values, stored as a plain string column (D-017).
 */
enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
            self::Other => 'Other',
        };
    }
}
