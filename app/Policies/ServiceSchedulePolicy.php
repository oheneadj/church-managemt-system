<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffPermission;
use App\Models\ServiceSchedule;
use App\Models\User;

/**
 * Who may see and change weekly schedules: the staff who may create services (members.edit, the church admin and the
 * pastor), because a schedule is a way of creating them (D-050).
 */
class ServiceSchedulePolicy
{
    /**
     * Whether the user may list schedules.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(StaffPermission::MembersEdit);
    }

    /**
     * Whether the user may set up a schedule.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo(StaffPermission::MembersEdit);
    }

    /**
     * Whether the user may change this schedule.
     */
    public function update(User $user, ServiceSchedule $schedule): bool
    {
        return $user->church_id === $schedule->church_id && $user->hasPermissionTo(StaffPermission::MembersEdit);
    }
}
