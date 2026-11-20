<?php

declare(strict_types=1);

namespace App\Actions\Contributions;

use App\DTOs\Contributions\ShowContributionDTO;
use App\Models\Contribution;
use App\Repositories\ContributionRepositoryInterface;

/**
 * Loads one gift with what the screen shows.
 */
final class ShowContributionAction
{
    /**
     * Takes the contribution repository.
     */
    public function __construct(private readonly ContributionRepositoryInterface $contributions) {}

    /**
     * Returns the gift with its member, fund, service and who recorded and voided it.
     */
    public function execute(ShowContributionDTO $dto): Contribution
    {
        return $this->contributions->withDetail($dto->contribution);
    }
}
