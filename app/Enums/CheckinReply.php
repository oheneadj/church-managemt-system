<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Every answer a member can get to a WhatsApp CHECKIN. The wording is in config/whatsapp.php, so it is changed in one place.
 */
enum CheckinReply: string
{
    case CheckedIn = 'checked_in';
    case AlreadyCheckedIn = 'already_checked_in';
    case UnknownNumber = 'unknown_number';
    case NoOpenService = 'no_open_service';
    case NeedsServiceCode = 'needs_service_code';

    /**
     * The reply text, with `:name`, `:service` and `:date` filled in where the text has them.
     *
     * @param  array<string, string>  $values
     */
    public function text(array $values = []): string
    {
        return __(config('whatsapp.replies.'.$this->value), $values);
    }

    /**
     * Human-readable text for screens and messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::CheckedIn => 'Checked in',
            self::AlreadyCheckedIn => 'Already checked in',
            self::UnknownNumber => 'Number not found',
            self::NoOpenService => 'No service open',
            self::NeedsServiceCode => 'Service code needed',
        };
    }
}
