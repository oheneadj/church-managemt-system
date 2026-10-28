<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

/**
 * Who may see and change departments and their members. Reading follows members.view, writing members.edit.
 */
class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('members.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('members.edit');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->church_id === $department->church_id && $user->hasPermissionTo('members.edit');
    }
}
