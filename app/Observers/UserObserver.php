<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;

/**
 * Ends a staff member's sessions the moment their access changes.
 * docs/05-data-safety.md and docs/15-security.md require this: deactivating a user, or changing
 * their phone number, must kill existing tokens immediately rather than waiting for them to expire.
 */
class UserObserver
{
    public function updated(User $user): void
    {
        $lostAccess = $user->wasChanged('is_active') && ! $user->is_active;

        if ($lostAccess || $user->wasChanged('phone')) {
            $user->tokens()->delete();
        }
    }

    public function deleted(User $user): void
    {
        $user->tokens()->delete();
    }
}
