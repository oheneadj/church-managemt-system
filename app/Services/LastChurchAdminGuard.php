<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StaffRole;
use App\Exceptions\LastChurchAdminException;
use App\Models\User;
use App\Repositories\UserRepositoryInterface;

/**
 * The rule that a church always keeps at least one active church admin (D-055 point 7). Actions that would take the role
 * or the active state from a staff member ask it first. It must be called inside the action's transaction: it locks the
 * church admins, so two changes made at the same moment cannot both remove the last one.
 */
class LastChurchAdminGuard
{
    /**
     * Takes the staff repository.
     */
    public function __construct(private readonly UserRepositoryInterface $users) {}

    /**
     * Throws when this staff member is the only active church admin. A person who is not an active church admin cannot
     * be the last one, so nothing is refused for them.
     *
     * @throws LastChurchAdminException
     */
    public function ensureOneRemainsWithout(User $staff): void
    {
        $admins = $this->users->lockActiveWithRole(StaffRole::ChurchAdmin);

        if (in_array($staff->id, $admins, true) && count($admins) === 1) {
            throw new LastChurchAdminException;
        }
    }
}
