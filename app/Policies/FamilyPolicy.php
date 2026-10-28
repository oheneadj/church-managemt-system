<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Family;
use App\Models\User;

/**
 * Who may see and change families. Reading follows members.view and writing follows members.edit.
 */
class FamilyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('members.view');
    }

    public function view(User $user, Family $family): bool
    {
        return $user->church_id === $family->church_id && $user->hasPermissionTo('members.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('members.edit');
    }

    public function update(User $user, Family $family): bool
    {
        return $user->church_id === $family->church_id && $user->hasPermissionTo('members.edit');
    }
}
