<?php

declare(strict_types=1);

namespace App\DTOs\Giving;

use App\Models\Contribution;

/**
 * What starting a public gift gives back: the gift, and whether it is one the giver already had waiting, so the page can say
 * "your payment is already waiting, approve it on your phone" and not start a second one.
 */
final readonly class PublicGift
{
    /**
     * Holds the gift and whether it was already waiting.
     */
    public function __construct(public Contribution $gift, public bool $alreadyWaiting) {}
}
