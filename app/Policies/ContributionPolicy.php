<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffPermission;
use App\Models\Contribution;
use App\Models\User;

/**
 * Who may see, record and void gifts. Seeing a single gift (giving.view) is kept apart from recording one
 * (giving.record) and voiding one (giving.edit), and from the totals (giving.summary), so a church can let a pastor
 * see named gifts without letting them change any (D-046).
 */
class ContributionPolicy
{
    /**
     * Whether the user may list gifts.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(StaffPermission::GivingView);
    }

    /**
     * Whether the user may see this gift.
     */
    public function view(User $user, Contribution $contribution): bool
    {
        return $user->church_id === $contribution->church_id && $user->hasPermissionTo(StaffPermission::GivingView);
    }

    /**
     * Whether the user may record a gift.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo(StaffPermission::GivingRecord);
    }

    /**
     * Whether the user may void this gift.
     */
    public function void(User $user, Contribution $contribution): bool
    {
        return $user->church_id === $contribution->church_id && $user->hasPermissionTo(StaffPermission::GivingEdit);
    }
}
