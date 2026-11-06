<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffPermission;
use App\Models\User;

/**
 * Who may read the message log. It holds phone numbers, email addresses and message text, so it needs `sms.send` (church
 * admin and pastor), not the `members.view` that every role has (D-055 point 4).
 */
class MessageLogPolicy
{
    /**
     * Whether the user may list the church's messages.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(StaffPermission::SmsSend);
    }
}
