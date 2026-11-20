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

    /**
     * The statuses shown in the directory when nothing else is asked for. The rest stay out of the default
     * view but can be searched (docs/03).
     *
     * @return list<MemberStatus>
     */
    public static function inDefaultDirectory(): array
    {
        return [self::Prospect, self::Active];
    }

    /**
     * Whether moving to this status takes the member out of active church life, which only the church admin may do.
     */
    public function leavesActiveLife(): bool
    {
        return in_array($this, [self::Inactive, self::Transferred, self::Deceased, self::Archived], true);
    }
}
