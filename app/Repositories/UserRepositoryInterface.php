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
     * Adds a staff user.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User;
}
