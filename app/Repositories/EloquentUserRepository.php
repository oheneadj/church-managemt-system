<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\StaffRole;
use App\Models\User;
use App\Scopes\ChurchScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Permission;

/**
 * Eloquent implementation of the staff user queries.
 */
class EloquentUserRepository implements UserRepositoryInterface
{
    /**
     * The staff member of the current church with this email, active or not, if any.
     */
    public function findByEmail(string $email): ?User
    {
        return User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();
    }

    /**
     * The staff member with this id, in any church, if there is one and they are not deleted.
     */
    public function findById(string $id): ?User
    {
        return User::query()->withoutGlobalScope(ChurchScope::class)->find($id);
    }

    /**
     * Sets a staff member's password and, when asked, marks their email as proven (once).
     */
    public function setPassword(User $user, string $password, bool $proveEmail = false): User
    {
        $user->forceFill(['password' => $password, 'email_verified_at' => $proveEmail ? ($user->email_verified_at ?? now()) : $user->email_verified_at])->save();

        return $user;
    }

    /**
     * Marks the staff member's email as proven, if it is not already.
     */
    public function markEmailVerified(User $user): User
    {
        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user;
    }

    /**
     * One page of the current church's staff, by name, with their roles and extra permissions.
     */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return User::query()->with(['roles', 'permissions'])->orderBy('name')->orderBy('id')->paginate($perPage);
    }

    /**
     * Whether a staff member of the current church already has this email, apart from the one given.
     */
    public function emailTaken(string $email, ?string $exceptUserId = null): bool
    {
        return User::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])
            ->when($exceptUserId !== null, fn (Builder $query) => $query->whereKeyNot($exceptUserId))
            ->exists();
    }

    /**
     * Whether a staff member of the current church already has this phone number, apart from the one given.
     */
    public function phoneTaken(string $phone, ?string $exceptUserId = null): bool
    {
        return User::query()
            ->where('phone', $phone)
            ->when($exceptUserId !== null, fn (Builder $query) => $query->whereKeyNot($exceptUserId))
            ->exists();
    }

    /**
     * Changes a staff member's own fields and returns them.
     */
    public function update(User $user, array $attributes): User
    {
        $user->update($attributes);

        return $user;
    }

    /**
     * Gives the staff member this one role, in place of any other.
     */
    public function changeRole(User $user, StaffRole $role): User
    {
        $user->syncRoles([$role->value]);

        return $user->load(['roles', 'permissions']);
    }

    /**
     * Switches a staff member on or off and returns them.
     */
    public function setActive(User $user, bool $isActive): User
    {
        $user->update(['is_active' => $isActive]);

        return $user;
    }

    /**
     * The names of the permissions the staff member was given directly, sorted.
     */
    public function extraPermissions(User $user): array
    {
        return $this->sortedNames($user->getDirectPermissions());
    }

    /**
     * The names of the permissions the staff member's role gives them, sorted.
     */
    public function rolePermissions(User $user): array
    {
        return $this->sortedNames($user->getPermissionsViaRoles());
    }

    /**
     * Gives these permissions directly and removes these ones.
     */
    public function changeExtraPermissions(User $user, array $grant, array $revoke): User
    {
        if ($grant !== []) {
            $user->givePermissionTo($grant);
        }

        if ($revoke !== []) {
            $user->revokePermissionTo($revoke);
        }

        return $user->load(['roles', 'permissions']);
    }

    /**
     * The ids of the current church's active staff who hold this role, locked until the transaction ends.
     */
    public function lockActiveWithRole(StaffRole $role): array
    {
        $ids = User::query()->role($role->value)->where('is_active', true)->lockForUpdate()->pluck('id')->map(fn ($id): string => (string) $id);

        return array_values($ids->all());
    }

    /**
     * Adds a staff user.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User
    {
        return User::query()->create($attributes);
    }

    /**
     * The permission names of a collection of permissions, sorted, so two lists can be compared.
     *
     * @param  Collection<int, Permission>  $permissions
     * @return list<string>
     */
    private function sortedNames(Collection $permissions): array
    {
        return array_values($permissions->map(fn (Permission $permission): string => (string) $permission->name)->sort()->all());
    }
}
