<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A staff member was invited by the church admin.
 */
class StaffInvited
{
    use Dispatchable;

    /**
     * Holds the new staff member, the admin who invited them and the role they were given.
     */
    public function __construct(
        public readonly User $staff,
        public readonly User $actor,
        public readonly string $role,
    ) {}
}
