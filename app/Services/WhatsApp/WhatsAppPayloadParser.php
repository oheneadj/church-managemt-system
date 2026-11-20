<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\DTOs\WhatsApp\InboundWhatsAppMessage;
use App\ValueObjects\PhoneNumber;
use Illuminate\Support\Collection;

/**
 * Reads the JSON Meta posts to the webhook (docs/14, section 3). Only text messages are returned: delivery receipts,
 * images, reactions and anything else are ignored, and a message missing a part is skipped rather than failing the rest.
 */
class WhatsAppPayloadParser
{
    /** What the webhook log calls a payload that carries messages. */
    public const EVENT_MESSAGE = 'message';

    /** What the webhook log calls a payload that carries only delivery receipts. */
    public const EVENT_STATUS = 'status';

    /** What the webhook log calls anything else. */
    public const EVENT_OTHER = 'other';

    /**
     * The kind of payload this is, for the webhook log.
     *
     * @param  array<string, mixed>  $payload
     */
    public function eventName(array $payload): string
    {
        $values = $this->values($payload);

        return match (true) {
            $values->contains(fn ($value) => ! empty($value['messages'])) => self::EVENT_MESSAGE,
            $values->contains(fn ($value) => ! empty($value['statuses'])) => self::EVENT_STATUS,
            default => self::EVENT_OTHER,
        };
    }

    /**
     * Every text message in the payload, in the order Meta sent them.
     *
     * @param  array<string, mixed>  $payload
     * @return list<InboundWhatsAppMessage>
     */
    public function messages(array $payload): array
    {
        $messages = [];

        foreach ($this->values($payload) as $value) {
            foreach ((array) ($value['messages'] ?? []) as $message) {
                $parsed = $this->message((array) $message);

                if ($parsed !== null) {
                    $messages[] = $parsed;
                }
            }
        }

        return $messages;
    }

    /**
     * The `value` part of every change in every entry.
     *
     * @param  array<string, mixed>  $payload
     * @return Collection<int, array<array-key, mixed>>
     */
    private function values(array $payload): Collection
    {
        return collect((array) ($payload['entry'] ?? []))
            ->flatMap(fn ($entry) => (array) data_get($entry, 'changes', []))
            ->map(fn ($change) => (array) data_get($change, 'value', []))
            ->values();
    }

    /**
     * One text message, or null when it is another kind or is missing its id, sender or words.
     *
     * @param  array<string, mixed>  $message
     */
    private function message(array $message): ?InboundWhatsAppMessage
    {
        $id = $message['id'] ?? null;
        $from = $message['from'] ?? null;
        $body = data_get($message, 'text.body');
        $phone = is_string($from) ? PhoneNumber::tryFrom('+'.$from) : null;

        if (($message['type'] ?? null) !== 'text' || ! is_string($id) || $phone === null || ! is_string($body)) {
            return null;
        }

        return new InboundWhatsAppMessage($id, $phone, $body);
    }
}
