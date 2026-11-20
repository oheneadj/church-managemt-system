<?php

declare(strict_types=1);

namespace App\DTOs\Messages;

/**
 * What a broadcast would reach and cost: how many people, how many parts the text is, the credits that takes (one for each
 * part, for each person), and whether the church has them.
 */
final readonly class BroadcastEstimate
{
    /**
     * Holds the counts and the church's balance.
     */
    public function __construct(
        public int $recipients,
        public int $partsPerMessage,
        public int $creditsNeeded,
        public int $creditBalance,
    ) {}

    /**
     * Whether the church's credits cover the whole broadcast.
     */
    public function enoughCredits(): bool
    {
        return $this->creditBalance >= $this->creditsNeeded;
    }
}
