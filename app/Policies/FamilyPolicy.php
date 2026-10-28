<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffPermission;
use App\Models\Family;
use App\Models\User;

/**
 * Who may see and change families. Reading follows members.view and writing follows members.edit.
 */
class FamilyPolicy
{
    /**
     * Whether the user may list familys.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(StaffPermission::MembersView);
    }

    /**
     * Whether the user may open this family.
     */
    public function view(User $user, Family $family): bool
    {
        return $user->church_id === $family->church_id && $user->hasPermissionTo(StaffPermission::MembersView);
    }

    /**
     * Whether the user may add a family.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo(StaffPermission::MembersEdit);
    }

    /**
     * Whether the user may change this family.
     */
    public function update(User $user, Family $family): bool
    {
        return $user->church_id === $family->church_id && $user->hasPermissionTo(StaffPermission::MembersEdit);
    }
}
