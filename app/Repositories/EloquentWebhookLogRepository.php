<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\WebhookSource;
use App\Models\WebhookLog;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Eloquent implementation of the webhook log queries.
 */
class EloquentWebhookLogRepository implements WebhookLogRepositoryInterface
{
    /**
     * Keeps one raw payload exactly as it arrived (it is stored encrypted).
     */
    public function create(WebhookSource $source, string $event, string $payload): WebhookLog
    {
        return WebhookLog::query()->create(['source' => $source, 'event' => $event, 'payload' => $payload]);
    }

    /**
     * Keeps one raw payload unless the provider's event id was already stored, in which case it returns null and keeps nothing.
     */
    public function createOnce(WebhookSource $source, string $event, string $eventId, string $payload): ?WebhookLog
    {
        try {
            $log = WebhookLog::query()->firstOrCreate(['source' => $source, 'event_id' => $eventId], ['event' => $event, 'payload' => $payload]);

            return $log->wasRecentlyCreated ? $log : null;
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /**
     * The logged payload with this id, if there is one.
     */
    public function find(string $id): ?WebhookLog
    {
        return WebhookLog::query()->find($id);
    }

    /**
     * Marks the payload as handled.
     */
    public function markProcessed(WebhookLog $log): void
    {
        $log->update(['processed' => true, 'processed_at' => now(), 'error' => null]);
    }

    /**
     * Records why a payload could not be handled, so it can be looked at and replayed.
     */
    public function markFailed(WebhookLog $log, string $error): void
    {
        $log->update(['error' => $error]);
    }
}
