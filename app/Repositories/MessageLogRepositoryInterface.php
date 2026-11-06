<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Messages\ListMessagesDTO;
use App\DTOs\ProviderReceipt;
use App\Models\MessageLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Every query on the message log lives behind this contract.
 */
interface MessageLogRepositoryInterface
{
    /**
     * Writes one line to the message log.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): MessageLog;

    /**
     * The message log row with this id, if there is one. Read from a job, which runs with no church in context.
     */
    public function find(string $id): ?MessageLog;

    /**
     * Records that the provider accepted the message.
     */
    public function markSent(MessageLog $log, ProviderReceipt $receipt): MessageLog;

    /**
     * Records that the message failed for good.
     */
    public function markFailed(MessageLog $log): MessageLog;

    /**
     * One page of the current church's messages, newest first, leaving out the types the church's log hides, narrowed by the
     * filters.
     *
     * @return LengthAwarePaginator<int, MessageLog>
     */
    public function paginate(ListMessagesDTO $filters): LengthAwarePaginator;

    /**
     * Removes the phone number and text from every logged message to this person, keeping the line itself.
     */
    public function anonymiseRecipient(string $recipientId): void;
}
