<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\PlatformAdmin;

/**
 * Eloquent implementation of the platform admin queries.
 */
class EloquentPlatformAdminRepository implements PlatformAdminRepositoryInterface
{
    /**
     * The platform admin with this email, active or not, if there is one.
     */
    public function findByEmail(string $email): ?PlatformAdmin
    {
        return PlatformAdmin::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();
    }

    /**
     * Whether an account with this email exists.
     */
    public function emailTaken(string $email): bool
    {
        return PlatformAdmin::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->exists();
    }

    /**
     * Creates an account.
     */
    public function create(array $attributes): PlatformAdmin
    {
        return PlatformAdmin::query()->create($attributes);
    }
}
