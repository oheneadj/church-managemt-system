<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The allowed member status values, stored as a plain string column (D-017).
 */
enum MemberStatus: string
{
    case Prospect = 'prospect';
    case Active = 'active';
    case Inactive = 'inactive';
    case Transferred = 'transferred';
    case Deceased = 'deceased';
    case Archived = 'archived';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Prospect => 'Prospect',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Transferred => 'Transferred',
            self::Deceased => 'Deceased',
            self::Archived => 'Archived',
        };
    }
}
