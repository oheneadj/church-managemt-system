<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffPermission;
use App\Models\Service;
use App\Models\User;

/**
 * Who may see and change services. Every signed-in staff member may read them (QA-10); changing them, opening and
 * closing check-in and recording attendance follow members.edit, which the church admin and the pastor hold.
 */
class ServicePolicy
{
    /**
     * Whether the user may list services. Any signed-in staff member may.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Whether the user may open this service. It must belong to the user's church.
     */
    public function view(User $user, Service $service): bool
    {
        return $user->church_id === $service->church_id;
    }

    /**
     * Whether the user may add a service.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo(StaffPermission::MembersEdit);
    }

    /**
     * Whether the user may change this service, open or close it, or record attendance for it.
     */
    public function update(User $user, Service $service): bool
    {
        return $user->church_id === $service->church_id && $user->hasPermissionTo(StaffPermission::MembersEdit);
    }
}
