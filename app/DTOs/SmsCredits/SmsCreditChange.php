<?php

declare(strict_types=1);

namespace App\DTOs\SmsCredits;

/**
 * A request to take credits for a text, or to give them back: whose credits, how many, and the message log row the
 * ledger line points at (one charge and one refund per message).
 */
final readonly class SmsCreditChange
{
    /**
     * Holds the church, the number of credits (always positive) and the message log id.
     */
    public function __construct(public string $churchId, public int $credits, public string $messageLogId) {}
}
