<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\WebhookSource;
use App\Repositories\WebhookLogRepositoryInterface;

/**
 * Takes in what PayPerlez posted: keeps the raw payload and nothing more, so PayPerlez gets its answer at once and does not
 * send it again (docs/14, section 7; docs/15, webhooks). An event whose id was already stored is dropped, because PayPerlez
 * resends the same event with the same id. The log stays unprocessed: turning an event into a paid, failed or paid-out gift
 * comes with the gift and payout flow (D-069 steps d and e), and the polling fallback covers payments until then.
 */
final class ReceivePayperlezWebhookAction
{
    /** Stored when PayPerlez names no event, so the log line is never empty. */
    public const EVENT_UNNAMED = 'unnamed';

    /**
     * Takes the repository this action uses.
     */
    public function __construct(private readonly WebhookLogRepositoryInterface $logs) {}

    /**
     * Logs the payload as it arrived. The event name and id are read only as labels; nothing is decided from them here.
     * A body without an id is kept without the duplicate check, because no id means nothing to compare.
     */
    public function execute(string $rawPayload): void
    {
        $decoded = json_decode($rawPayload, true);
        $decoded = is_array($decoded) ? $decoded : [];

        $event = $this->label($decoded['type'] ?? $decoded['event'] ?? null) ?? self::EVENT_UNNAMED;
        $eventId = $this->label($decoded['id'] ?? null);

        if ($eventId === null) {
            $this->logs->create(WebhookSource::Payperlez, $event, $rawPayload);

            return;
        }

        $this->logs->createOnce(WebhookSource::Payperlez, $event, $eventId, $rawPayload);
    }

    /**
     * A short text label from a value, or null when it is not text or a number or is too long to be one.
     */
    private function label(mixed $value): ?string
    {
        $text = is_string($value) || is_int($value) ? (string) $value : '';

        return $text !== '' && mb_strlen($text) <= 100 ? $text : null;
    }
}
