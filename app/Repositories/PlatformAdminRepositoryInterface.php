<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\PlatformAdmin;

/**
 * Every query on platform admin accounts lives behind this contract (D-069).
 */
interface PlatformAdminRepositoryInterface
{
    /**
     * The platform admin with this email, active or not, if there is one. Capitals and spaces around the email do not matter.
     */
    public function findByEmail(string $email): ?PlatformAdmin;

    /**
     * Whether an account with this email exists.
     */
    public function emailTaken(string $email): bool;

    /**
     * Creates an account.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PlatformAdmin;
}
