<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The allowed milestone type values, stored as a plain string column (D-017).
 */
enum MilestoneType: string
{
    case Dedication = 'dedication';
    case Baptism = 'baptism';
    case Confirmation = 'confirmation';
    case FirstCommunion = 'first_communion';
    case MembershipClass = 'membership_class';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Dedication => 'Dedication',
            self::Baptism => 'Baptism',
            self::Confirmation => 'Confirmation',
            self::FirstCommunion => 'First communion',
            self::MembershipClass => 'Membership class',
        };
    }
}
