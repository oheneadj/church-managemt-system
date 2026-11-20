<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\WhatsApp\HandleWhatsAppCheckinAction;
use App\Repositories\WebhookLogRepositoryInterface;
use App\Services\WhatsApp\WhatsAppPayloadParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles one logged Meta webhook on a worker: reads the messages in it and checks each sender in. It takes the log's
 * id, not the payload, so the personal data stays in the encrypted log. It is safe to run again: a message already
 * handled is skipped (see HandleWhatsAppCheckinAction), so a retry after a crash does no harm.
 */
class ProcessWhatsAppWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    /**
     * Takes the id of the logged webhook.
     */
    public function __construct(public readonly string $webhookLogId) {}

    /**
     * Checks in every sender whose message is a CHECKIN, then marks the log handled.
     */
    public function handle(
        WebhookLogRepositoryInterface $logs,
        WhatsAppPayloadParser $parser,
        HandleWhatsAppCheckinAction $checkin,
    ): void {
        $log = $logs->find($this->webhookLogId);

        if ($log === null || $log->processed) {
            return;
        }

        foreach ($parser->messages((array) json_decode($log->payload, true)) as $message) {
            $checkin->execute($message);
        }

        $logs->markProcessed($log);
    }

    /**
     * Records the failure on the log, so the payload can be looked at and replayed. Nothing personal goes in the log line.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('A WhatsApp webhook could not be processed', ['webhook_log_id' => $this->webhookLogId, 'error' => $exception::class]);

        $log = app(WebhookLogRepositoryInterface::class)->find($this->webhookLogId);

        if ($log !== null) {
            app(WebhookLogRepositoryInterface::class)->markFailed($log, $exception::class);
        }
    }
}
