<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use App\Scopes\ChurchScope;

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
     * Adds a staff user.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User
    {
        return User::query()->create($attributes);
    }
}
