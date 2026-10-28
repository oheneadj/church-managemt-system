<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Church;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A church cancelled. Its grace period has started. Fired by the Action.
 */
class ChurchCancelled
{
    use Dispatchable;

    /**
     * Holds the church.
     */
    public function __construct(public readonly Church $church) {}
}
