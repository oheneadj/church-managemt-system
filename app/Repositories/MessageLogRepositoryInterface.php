<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\SmsReceipt;
use App\Models\MessageLog;

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
    public function markSent(MessageLog $log, SmsReceipt $receipt): MessageLog;

    /**
     * Records that the message failed for good.
     */
    public function markFailed(MessageLog $log): MessageLog;

    /**
     * Removes the phone number and text from every logged message to this person, keeping the line itself.
     */
    public function anonymiseRecipient(string $recipientId): void;
}
