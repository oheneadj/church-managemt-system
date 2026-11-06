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
     * The active staff member of the current church with this phone number, if any.
     */
    public function findActiveByPhone(string $phone): ?User
    {
        return User::query()->where('phone', $phone)->where('is_active', true)->first();
    }

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
     * Adds a staff user.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User
    {
        return User::query()->create($attributes);
    }
}
