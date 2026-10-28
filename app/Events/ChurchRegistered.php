<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A new church started on the platform with its first admin. Fired by the Action inside its transaction.
 */
class ChurchRegistered
{
    use Dispatchable;

    /**
     * Holds the church and its first admin.
     */
    public function __construct(public readonly Church $church, public readonly User $admin) {}
}
