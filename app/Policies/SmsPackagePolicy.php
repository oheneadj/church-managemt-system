<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PlatformAdmin;

/**
 * Who may manage the SMS credit packages (D-069): any platform admin whose account is switched on. A church's staff are not
 * platform admins and never reach this.
 */
class SmsPackagePolicy
{
    /**
     * Whether the admin may list the packages.
     */
    public function viewAny(PlatformAdmin $admin): bool
    {
        return $admin->is_active;
    }

    /**
     * Whether the admin may add a package.
     */
    public function create(PlatformAdmin $admin): bool
    {
        return $admin->is_active;
    }

    /**
     * Whether the admin may change a package.
     */
    public function update(PlatformAdmin $admin): bool
    {
        return $admin->is_active;
    }
}
