<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffPermission;
use App\Models\Pledge;
use App\Models\User;

/**
 * Who may see, make and change pledges. A pledge names a member and an amount, so it is individual giving data like a
 * gift (D-046, D-047): reading needs giving.view, making one giving.record, changing one giving.edit.
 */
class PledgePolicy
{
    /**
     * Whether the user may list pledges.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(StaffPermission::GivingView);
    }

    /**
     * Whether the user may see this pledge.
     */
    public function view(User $user, Pledge $pledge): bool
    {
        return $user->church_id === $pledge->church_id && $user->hasPermissionTo(StaffPermission::GivingView);
    }

    /**
     * Whether the user may make a pledge.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo(StaffPermission::GivingRecord);
    }

    /**
     * Whether the user may change this pledge.
     */
    public function update(User $user, Pledge $pledge): bool
    {
        return $user->church_id === $pledge->church_id && $user->hasPermissionTo(StaffPermission::GivingEdit);
    }
}
