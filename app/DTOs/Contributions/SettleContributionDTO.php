<?php

declare(strict_types=1);

namespace App\DTOs\Contributions;

use App\DTOs\Payments\CollectionResult;

/**
 * The provider's word on a gift's payment, to be turned into the gift's outcome: the gift's id and what the provider said.
 */
final readonly class SettleContributionDTO
{
    /**
     * Holds the gift's id and the provider's answer.
     */
    public function __construct(public string $contributionId, public CollectionResult $result) {}
}
