<?php

declare(strict_types=1);

namespace App\DTOs\Messages;

use App\Models\Broadcast;

/**
 * A broadcast that was accepted and queued, with the estimate it was accepted on.
 */
final readonly class QueuedBroadcast
{
    /**
     * Holds the broadcast and its estimate.
     */
    public function __construct(public Broadcast $broadcast, public BroadcastEstimate $estimate) {}
}
