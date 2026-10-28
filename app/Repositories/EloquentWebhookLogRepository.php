<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\WebhookSource;
use App\Models\WebhookLog;

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
