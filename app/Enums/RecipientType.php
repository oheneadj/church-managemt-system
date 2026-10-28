<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The allowed recipient type values, stored as a plain string column (D-017).
 */
enum RecipientType: string
{
    case Member = 'member';
    case User = 'user';
    case Visitor = 'visitor';
    case Applicant = 'applicant';
    case Unknown = 'unknown';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Member => 'Member',
            self::User => 'User',
            self::Visitor => 'Visitor',
            self::Applicant => 'Applicant',
            self::Unknown => 'Unknown number',
        };
    }
}
