<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\DTOs\Staff\ChangeStaffStatusDTO;
use App\Events\StaffStatusChanged;
use App\Exceptions\LastChurchAdminException;
use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use App\Services\LastChurchAdminGuard;
use Illuminate\Support\Facades\DB;

/**
 * Deactivates or reactivates a staff member (D-055 point 7). Deactivating ends their tokens at once (UserObserver) and
 * their browser session on the next request (EnsureStaffIsCurrent). The last active church admin cannot be deactivated.
 * The change is written to the history; asking for the state the person is already in changes and records nothing.
 *
 * @throws LastChurchAdminException
 */
final class ChangeStaffStatusAction
{
    /**
     * Takes the staff repository and the last-church-admin rule.
     */
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly LastChurchAdminGuard $lastAdmin,
    ) {}

    /**
     * Applies the change.
     */
    public function execute(ChangeStaffStatusDTO $dto): User
    {
        return DB::transaction(function () use ($dto): User {
            $staff = $dto->staff;
            $wasActive = $staff->is_active;

            if ($wasActive === $dto->isActive) {
                return $staff->load(['roles', 'permissions']);
            }

            if (! $dto->isActive) {
                $this->lastAdmin->ensureOneRemainsWithout($staff);
            }

            $this->users->setActive($staff, $dto->isActive);
            StaffStatusChanged::dispatch($staff, $dto->actor, $wasActive);

            return $staff->load(['roles', 'permissions']);
        });
    }
}
