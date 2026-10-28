<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\WebhookSource;
use App\Models\WebhookLog;

/**
 * Every query on the raw webhook log lives behind this contract.
 */
interface WebhookLogRepositoryInterface
{
    /**
     * Keeps one raw payload exactly as it arrived (it is stored encrypted).
     */
    public function create(WebhookSource $source, string $event, string $payload): WebhookLog;

    /**
     * The logged payload with this id, if there is one.
     */
    public function find(string $id): ?WebhookLog;

    /**
     * Marks the payload as handled.
     */
    public function markProcessed(WebhookLog $log): void;

    /**
     * Records why a payload could not be handled, so it can be looked at and replayed.
     */
    public function markFailed(WebhookLog $log, string $error): void;
}
