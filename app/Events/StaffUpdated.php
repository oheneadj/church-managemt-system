<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A staff member's name, phone or role was changed by the church admin. Carries the old and new values of what changed.
 */
class StaffUpdated
{
    use Dispatchable;

    /**
     * Holds the staff member, the admin, and the old and new values.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function __construct(
        public readonly User $staff,
        public readonly User $actor,
        public readonly array $old,
        public readonly array $new,
    ) {}
}
