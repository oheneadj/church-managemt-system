<?php

declare(strict_types=1);

namespace App\Actions\Pledges;

use App\DTOs\Pledges\ListPledgesDTO;
use App\Models\Pledge;
use App\Repositories\PledgeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lists one page of the church's pledges.
 */
final class ListPledgesAction
{
    /**
     * Takes the pledge repository.
     */
    public function __construct(private readonly PledgeRepositoryInterface $pledges) {}

    /**
     * Lists one page of pledges.
     *
     * @return LengthAwarePaginator<int, Pledge>
     */
    public function execute(ListPledgesDTO $dto): LengthAwarePaginator
    {
        return $this->pledges->paginate($dto);
    }
}
