<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Scopes\ChurchScope;

/**
 * Eloquent implementation of the broadcast queries.
 */
class EloquentBroadcastRepository implements BroadcastRepositoryInterface
{
    /**
     * Records a new broadcast.
     */
    public function create(array $attributes): Broadcast
    {
        return Broadcast::query()->create($attributes);
    }

    /**
     * The broadcast with this id, in any church.
     */
    public function find(string $id): ?Broadcast
    {
        return Broadcast::query()->withoutGlobalScope(ChurchScope::class)->find($id);
    }

    /**
     * Records that a worker started turning the broadcast into texts.
     */
    public function markSending(Broadcast $broadcast): Broadcast
    {
        $broadcast->update(['status' => BroadcastStatus::Sending]);

        return $broadcast;
    }

    /**
     * Records that every text is queued, and how many people it reached.
     */
    public function markDispatched(Broadcast $broadcast, int $recipients): Broadcast
    {
        $broadcast->update(['status' => BroadcastStatus::Dispatched, 'recipient_count' => $recipients]);

        return $broadcast;
    }

    /**
     * Records that the broadcast failed before every text was queued.
     */
    public function markFailed(Broadcast $broadcast): Broadcast
    {
        $broadcast->update(['status' => BroadcastStatus::Failed]);

        return $broadcast;
    }

    /**
     * Replaces the message and forgets the filters of every broadcast of the current church.
     */
    public function clearDetails(): void
    {
        Broadcast::query()->withTrashed()->update(['message' => config('privacy.erased_marker'), 'filters' => null]);
    }
}
