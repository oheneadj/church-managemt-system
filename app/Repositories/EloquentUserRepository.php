<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;

/**
 * Eloquent implementation of the staff user queries.
 */
class EloquentUserRepository implements UserRepositoryInterface
{
    public function findActiveByPhone(string $phone): ?User
    {
        return User::query()->where('phone', $phone)->where('is_active', true)->first();
    }
}
