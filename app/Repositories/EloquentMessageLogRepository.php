<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Messages\ListMessagesDTO;
use App\DTOs\ProviderReceipt;
use App\Enums\MessageStatus;
use App\Models\MessageLog;
use App\Scopes\ChurchScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent implementation of the message log writes.
 */
class EloquentMessageLogRepository implements MessageLogRepositoryInterface
{
    /**
     * One page of the current church's messages, newest first, narrowed by the filters.
     */
    public function paginate(ListMessagesDTO $filters): LengthAwarePaginator
    {
        return MessageLog::query()
            ->whereNotIn('message_type', $filters->hiddenTypes)
            ->when($filters->channel !== null, fn (Builder $q) => $q->where('channel', $filters->channel))
            ->when($filters->messageType !== null, fn (Builder $q) => $q->where('message_type', $filters->messageType))
            ->when($filters->from !== null, fn (Builder $q) => $q->whereDate('created_at', '>=', $filters->from))
            ->when($filters->to !== null, fn (Builder $q) => $q->whereDate('created_at', '<=', $filters->to))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filters->perPage);
    }

    /**
     * The ids of the members who already have a text line for this broadcast.
     */
    public function recipientIdsForBroadcast(string $broadcastId): array
    {
        $ids = MessageLog::query()->withoutGlobalScope(ChurchScope::class)->where('broadcast_id', $broadcastId)->whereNotNull('recipient_id')->pluck('recipient_id')->map(fn ($id): string => (string) $id);

        return array_values($ids->all());
    }

    /**
     * Writes one line to the message log.
     */
    public function create(array $attributes): MessageLog
    {
        return MessageLog::query()->create($attributes);
    }

    /**
     * The message log row with this id, if there is one.
     */
    public function find(string $id): ?MessageLog
    {
        return MessageLog::query()->withoutGlobalScope(ChurchScope::class)->find($id);
    }

    /**
     * Records that the provider accepted the message.
     */
    public function markSent(MessageLog $log, ProviderReceipt $receipt): MessageLog
    {
        $log->update(['status' => MessageStatus::Sent, 'provider_reference' => $receipt->reference, 'provider_response' => $receipt->response, 'sent_at' => now()]);

        return $log;
    }

    /**
     * Records that the message failed for good.
     */
    public function markFailed(MessageLog $log): MessageLog
    {
        $log->update(['status' => MessageStatus::Failed]);

        return $log;
    }

    /**
     * Removes the phone number and text from every logged message to this person, keeping the line itself.
     */
    public function anonymiseRecipient(string $recipientId): void
    {
        MessageLog::query()->where('recipient_id', $recipientId)->update(['recipient_phone' => null, 'recipient_email' => null, 'content' => config('privacy.erased_marker')]);
    }
}
