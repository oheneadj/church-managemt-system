<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A church's profile or settings were changed by the church admin. Carries the old and new values of what changed.
 */
class ChurchUpdated
{
    use Dispatchable;

    /**
     * Holds the church, the admin, and the old and new values.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function __construct(
        public readonly Church $church,
        public readonly User $actor,
        public readonly array $old,
        public readonly array $new,
    ) {}
}
