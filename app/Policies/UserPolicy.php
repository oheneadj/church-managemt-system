<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffPermission;
use App\Models\User;

/**
 * Who may manage the church's staff (docs/08, Staff). Only a user with `staff.manage` (the church admin) may list, invite
 * or change staff, and only within their own church.
 */
class UserPolicy
{
    /**
     * Whether the user may list the church's staff.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(StaffPermission::StaffManage);
    }

    /**
     * Whether the user may invite a staff member.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo(StaffPermission::StaffManage);
    }

    /**
     * Whether the user may change this staff member.
     */
    public function update(User $user, User $staff): bool
    {
        return $user->church_id === $staff->church_id && $user->hasPermissionTo(StaffPermission::StaffManage);
    }
}
