<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A church's settings were changed by the church admin. Carries the old and new values of the settings that changed.
 */
class ChurchSettingsUpdated
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
