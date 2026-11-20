<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Broadcast;

/**
 * Every query on broadcasts lives behind this contract.
 */
interface BroadcastRepositoryInterface
{
    /**
     * Records a new broadcast.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Broadcast;

    /**
     * The broadcast with this id, if there is one. Read from a job, which runs with no church in context.
     */
    public function find(string $id): ?Broadcast;

    /**
     * Records that a worker started turning the broadcast into texts.
     */
    public function markSending(Broadcast $broadcast): Broadcast;

    /**
     * Records that every text of the broadcast is queued, and how many people it reached.
     */
    public function markDispatched(Broadcast $broadcast, int $recipients): Broadcast;

    /**
     * Records that the broadcast failed before every text was queued.
     */
    public function markFailed(Broadcast $broadcast): Broadcast;

    /**
     * Replaces the message and forgets the filters of every broadcast of the current church, for a church purge (a message can
     * name people). The record of who sent a broadcast and when stays.
     */
    public function clearDetails(): void;
}
