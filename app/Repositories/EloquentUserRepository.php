<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;

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
}
