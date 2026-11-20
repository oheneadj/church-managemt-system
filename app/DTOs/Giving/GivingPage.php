<?php

declare(strict_types=1);

namespace App\DTOs\Giving;

/**
 * What the public giving page shows: the church's name, whether it can take gifts at all, the funds a person can choose and the
 * limits and processing fee the page needs to tell them what they will pay. Amounts and the rate are decimal strings.
 */
final readonly class GivingPage
{
    /**
     * Holds the page's facts.
     *
     * @param  list<array{id: string, name: string, description: string|null}>  $funds
     * @param  numeric-string  $minGift
     * @param  numeric-string  $maxGift
     * @param  numeric-string  $processingFeePercent
     */
    public function __construct(
        public string $churchName,
        public bool $available,
        public array $funds,
        public string $minGift,
        public string $maxGift,
        public string $processingFeePercent,
    ) {}
}
