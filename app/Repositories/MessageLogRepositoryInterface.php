<?php

declare(strict_types=1);

namespace App\Repositories;

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
     * Removes the phone number and text from every logged message to this person, keeping the line itself.
     */
    public function anonymiseRecipient(string $recipientId): void;
}
