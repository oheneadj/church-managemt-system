<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * The days of the week, stored as a plain string column (D-017), for a service schedule.
 */
enum Weekday: string
{
    case Sunday = 'sunday';
    case Monday = 'monday';
    case Tuesday = 'tuesday';
    case Wednesday = 'wednesday';
    case Thursday = 'thursday';
    case Friday = 'friday';
    case Saturday = 'saturday';

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * The day's number in Carbon (0 for Sunday to 6 for Saturday), the only place text becomes a number.
     */
    public function carbonDay(): int
    {
        return match ($this) {
            self::Sunday => CarbonInterface::SUNDAY,
            self::Monday => CarbonInterface::MONDAY,
            self::Tuesday => CarbonInterface::TUESDAY,
            self::Wednesday => CarbonInterface::WEDNESDAY,
            self::Thursday => CarbonInterface::THURSDAY,
            self::Friday => CarbonInterface::FRIDAY,
            self::Saturday => CarbonInterface::SATURDAY,
        };
    }
}
