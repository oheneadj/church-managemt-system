<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A staff member was deactivated or reactivated by the church admin.
 */
class StaffStatusChanged
{
    use Dispatchable;

    /**
     * Holds the staff member, the admin, and whether the staff member was active before.
     */
    public function __construct(
        public readonly User $staff,
        public readonly User $actor,
        public readonly bool $wasActive,
    ) {}
}
