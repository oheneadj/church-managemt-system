<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\DTOs\Staff\UpdateStaffDTO;
use App\Enums\StaffRole;
use App\Events\StaffUpdated;
use App\Exceptions\LastChurchAdminException;
use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use App\Services\LastChurchAdminGuard;
use Illuminate\Support\Facades\DB;

/**
 * Changes a staff member's name, phone and role (D-055 point 7). A change of role takes effect on the person's next request,
 * because permissions are read on every request. Taking the church admin role from the last active church admin is refused.
 * What really changed is written to the history, and nothing is written when nothing changed.
 *
 * @throws LastChurchAdminException
 */
final class UpdateStaffAction
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
    public function execute(UpdateStaffDTO $dto): User
    {
        return DB::transaction(function () use ($dto): User {
            $staff = $dto->staff;
            $old = [];
            $new = [];

            foreach ($dto->attributes as $field => $value) {
                if ($staff->{$field} !== $value) {
                    $old[$field] = $staff->{$field};
                    $new[$field] = $value;
                }
            }

            $currentRole = $staff->getRoleNames()->first();

            if ($dto->role !== null && $dto->role->value !== $currentRole) {
                if ($currentRole === StaffRole::ChurchAdmin->value) {
                    $this->lastAdmin->ensureOneRemainsWithout($staff);
                }

                $old['role'] = $currentRole;
                $new['role'] = $dto->role->value;
                $this->users->changeRole($staff, $dto->role);
            }

            $own = array_diff_key($new, ['role' => true]);
            if ($own !== []) {
                $this->users->update($staff, $own);
            }

            if ($new !== []) {
                StaffUpdated::dispatch($staff, $dto->actor, $old, $new);
            }

            return $staff->load(['roles', 'permissions']);
        });
    }
}
