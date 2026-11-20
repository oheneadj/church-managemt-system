<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffPermission;
use App\Models\FundType;
use App\Models\User;

/**
 * Who may see and change fund types. Every signed-in staff member may read them, because giving screens and reports
 * show their names; only the church admin may add, rename or switch them off (docs/08, Fund Types).
 */
class FundTypePolicy
{
    /**
     * Whether the user may list fund types.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Whether the user may add a fund type.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo(StaffPermission::FundTypesManage);
    }

    /**
     * Whether the user may change this fund type.
     */
    public function update(User $user, FundType $fundType): bool
    {
        return $user->church_id === $fundType->church_id && $user->hasPermissionTo(StaffPermission::FundTypesManage);
    }
}
