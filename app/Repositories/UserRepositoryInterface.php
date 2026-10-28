<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;

/**
 * Every query on staff users lives behind this contract.
 */
interface UserRepositoryInterface
{
    /**
     * The active staff member of the current church with this phone number, if there is one.
     */
    public function findActiveByPhone(string $phone): ?User;

    /**
     * Adds a staff user.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User;
}
