<?php

declare(strict_types=1);

namespace App\Actions\Pledges;

use App\DTOs\Pledges\ShowPledgeDTO;
use App\Models\Pledge;
use App\Repositories\PledgeRepositoryInterface;

/**
 * Loads one pledge with the gifts that pay it.
 */
final class ShowPledgeAction
{
    /**
     * Takes the pledge repository.
     */
    public function __construct(private readonly PledgeRepositoryInterface $pledges) {}

    /**
     * Returns the pledge with its member, fund and every gift linked to it (voided ones are marked).
     */
    public function execute(ShowPledgeDTO $dto): Pledge
    {
        return $this->pledges->withDetail($dto->pledge, true);
    }
}
