<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffPermission;
use App\Models\Department;
use App\Models\User;

/**
 * Who may see and change departments and their members. Reading follows members.view, writing members.edit.
 */
class DepartmentPolicy
{
    /**
     * Whether the user may list departments.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(StaffPermission::MembersView);
    }

    /**
     * Whether the user may add a department.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo(StaffPermission::MembersEdit);
    }

    /**
     * Whether the user may change this department.
     */
    public function update(User $user, Department $department): bool
    {
        return $user->church_id === $department->church_id && $user->hasPermissionTo(StaffPermission::MembersEdit);
    }
}
