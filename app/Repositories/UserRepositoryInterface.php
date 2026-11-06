<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Every query on staff users lives behind this contract.
 */
interface UserRepositoryInterface
{
    /**
     * The staff member of the current church with this email, active or not, if there is one. Capitals and spaces around
     * the email do not matter. A deleted staff member is never found.
     */
    public function findByEmail(string $email): ?User;

    /**
     * The staff member with this id, in any church, if there is one and they are not deleted. It is for work on a worker,
     * which has no church in context; a request must use the lookups that stay inside the current church.
     */
    public function findById(string $id): ?User;

    /**
     * Sets a staff member's password (hashed by the model) and, when asked, marks their email as proven. The password is
     * not mass assignable on purpose, so this is the only way it is set.
     */
    public function setPassword(User $user, string $password, bool $proveEmail = false): User;

    /**
     * Marks the staff member's email as proven, if it is not already.
     */
    public function markEmailVerified(User $user): User;

    /**
     * One page of the current church's staff, by name, with their roles and extra permissions. Deactivated staff are included.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(int $perPage): LengthAwarePaginator;

    /**
     * Whether a staff member of the current church already has this email (capitals do not matter), apart from the one given.
     */
    public function emailTaken(string $email, ?string $exceptUserId = null): bool;

    /**
     * Whether a staff member of the current church already has this phone number, apart from the one given.
     */
    public function phoneTaken(string $phone, ?string $exceptUserId = null): bool;

    /**
     * Changes a staff member's own fields (name and phone) and returns them.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, array $attributes): User;

    /**
     * Gives the staff member this one role, in place of any other, and returns them with their roles loaded.
     */
    public function changeRole(User $user, StaffRole $role): User;

    /**
     * Switches a staff member on or off and returns them. Switching off ends their tokens (UserObserver).
     */
    public function setActive(User $user, bool $isActive): User;

    /**
     * The names of the permissions the staff member was given directly, on top of their role, sorted.
     *
     * @return list<string>
     */
    public function extraPermissions(User $user): array;

    /**
     * The names of the permissions the staff member's role gives them, sorted.
     *
     * @return list<string>
     */
    public function rolePermissions(User $user): array;

    /**
     * Gives the staff member these permissions directly and removes these ones, and returns them with their roles and
     * permissions loaded. A permission to remove must be one that was given directly.
     *
     * @param  list<string>  $grant
     * @param  list<string>  $revoke
     */
    public function changeExtraPermissions(User $user, array $grant, array $revoke): User;

    /**
     * The ids of the current church's active staff who hold this role, locked until the transaction ends, so two changes
     * made at the same moment cannot both remove the last one.
     *
     * @return list<string>
     */
    public function lockActiveWithRole(StaffRole $role): array;

    /**
     * Adds a staff user.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User;
}
