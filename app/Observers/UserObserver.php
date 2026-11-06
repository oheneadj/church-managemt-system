<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;

/**
 * Ends a staff member's sessions the moment their access changes.
 * docs/05-data-safety.md and docs/15-security.md require this: deactivating a user, or changing
 * their email or password (D-054), must kill existing tokens immediately rather than waiting for them to expire.
 */
class UserObserver
{
    /**
     * Ends the staff member's tokens when they are deactivated or change email or password. The phone number is only a
     * contact number now and does not matter.
     */
    public function updated(User $user): void
    {
        $lostAccess = $user->wasChanged('is_active') && ! $user->is_active;

        if ($lostAccess || $user->wasChanged(['email', 'password'])) {
            $user->tokens()->delete();
        }
    }

    /**
     * Ends all of a deleted staff member's tokens.
     */
    public function deleted(User $user): void
    {
        $user->tokens()->delete();
    }
}
