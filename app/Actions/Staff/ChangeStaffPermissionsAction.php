<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\DTOs\Staff\ChangeStaffPermissionsDTO;
use App\Events\StaffUpdated;
use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Gives a staff member single permissions on top of their role, and takes such extras away again (D-055 point 7). Extras
 * only: a permission the role already gives is not copied into an extra, and one the role gives cannot be removed (the
 * request refuses it). The change applies on the person's next request. The extras before and after are written to the
 * history, and nothing is written when nothing changed.
 */
final class ChangeStaffPermissionsAction
{
    /**
     * Takes the staff repository.
     */
    public function __construct(private readonly UserRepositoryInterface $users) {}

    /**
     * Applies the change.
     */
    public function execute(ChangeStaffPermissionsDTO $dto): User
    {
        return DB::transaction(function () use ($dto): User {
            $staff = $dto->staff;
            $before = $this->users->extraPermissions($staff);
            $fromRole = $this->users->rolePermissions($staff);

            $this->users->changeExtraPermissions(
                $staff,
                array_values(array_diff($dto->grant, $fromRole, $before)),
                $dto->revoke,
            );

            $after = $this->users->extraPermissions($staff);

            if ($after !== $before) {
                StaffUpdated::dispatch($staff, $dto->actor, ['extra_permissions' => $before], ['extra_permissions' => $after]);
            }

            return $staff;
        });
    }
}
