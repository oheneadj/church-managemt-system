<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaffPermission;
use App\Models\SmsCreditPurchase;
use App\Models\User;

/**
 * Who may buy SMS credits and see a purchase (D-069, D-061 point 4). Only staff who manage the church (`church.settings`, the
 * church admin) may see the packages on sale, start a purchase or read one, and only for their own church.
 */
class SmsCreditPurchasePolicy
{
    /**
     * Whether the user may see the packages on sale.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(StaffPermission::ChurchSettings);
    }

    /**
     * Whether the user may start a purchase for their church.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo(StaffPermission::ChurchSettings);
    }

    /**
     * Whether the user may read this purchase.
     */
    public function view(User $user, SmsCreditPurchase $purchase): bool
    {
        return $user->church_id === $purchase->church_id && $user->hasPermissionTo(StaffPermission::ChurchSettings);
    }
}
