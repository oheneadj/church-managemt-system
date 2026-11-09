<?php

declare(strict_types=1);

namespace App\DTOs\SmsCredits;

use App\DTOs\Payments\CollectionResult;

/**
 * What the provider said about one purchase's payment, to be settled: the purchase's id and the provider's normalised answer.
 */
final readonly class SettleSmsCreditPurchaseDTO
{
    /**
     * Holds the purchase id and the provider's answer.
     */
    public function __construct(public string $purchaseId, public CollectionResult $result) {}
}
