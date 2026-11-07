<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffPermission;
use App\Models\User;

/**
 * Who may read the recent activity feed made from the change history (D-068): anyone who may read reports. Each kind of line
 * is then shown only to those who also hold the permission that guards what it is about (ActivityKind::permission()).
 */
class AuditLogPolicy
{
    /**
     * Whether the user may read the activity feed.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(StaffPermission::ReportsView);
    }
}
