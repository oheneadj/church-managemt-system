<?php

declare(strict_types=1);

namespace App\Actions\WhatsApp;

use App\Enums\WebhookSource;
use App\Jobs\ProcessWhatsAppWebhookJob;
use App\Repositories\WebhookLogRepositoryInterface;
use App\Services\WhatsApp\WhatsAppPayloadParser;

/**
 * Takes in what Meta posted: keeps the raw payload, then queues the work. It does nothing slow, so Meta gets its
 * answer at once and does not send the same thing again (docs/14, section 3; docs/15, webhooks).
 */
final class ReceiveWhatsAppWebhookAction
{
    /**
     * Takes the services this action uses.
     */
    public function __construct(
        private readonly WebhookLogRepositoryInterface $logs,
        private readonly WhatsAppPayloadParser $parser,
    ) {}

    /**
     * Logs the payload as it arrived and queues its processing. A body that is not JSON is logged with its reason and not processed.
     */
    public function execute(string $rawPayload): void
    {
        $decoded = json_decode($rawPayload, true);

        if (! is_array($decoded)) {
            $log = $this->logs->create(WebhookSource::MetaWhatsapp, WhatsAppPayloadParser::EVENT_OTHER, $rawPayload);
            $this->logs->markFailed($log, 'The body was not valid JSON.');

            return;
        }

        $log = $this->logs->create(WebhookSource::MetaWhatsapp, $this->parser->eventName($decoded), $rawPayload);

        ProcessWhatsAppWebhookJob::dispatch($log->id)->onQueue('notifications');
    }
}
