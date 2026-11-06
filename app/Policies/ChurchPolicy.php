<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffPermission;
use App\Models\Church;
use App\Models\User;

/**
 * Who may read and change a church's settings (docs/08, Church Settings; D-055 point 6). Any signed-in staff member may read
 * their own church's settings, because nothing in them is secret; only `church.settings` (the church admin) changes them.
 */
class ChurchPolicy
{
    /**
     * Whether the user may read this church's settings.
     */
    public function viewSettings(User $user, Church $church): bool
    {
        return $user->church_id === $church->id;
    }

    /**
     * Whether the user may change this church's settings.
     */
    public function updateSettings(User $user, Church $church): bool
    {
        return $user->church_id === $church->id && $user->hasPermissionTo(StaffPermission::ChurchSettings);
    }
}
