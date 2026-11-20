<?php

declare(strict_types=1);

namespace App\Actions\Contributions;

use App\DTOs\Contributions\ListContributionsDTO;
use App\Models\Contribution;
use App\Repositories\ContributionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lists one page of the church's gifts.
 */
final class ListContributionsAction
{
    /**
     * Takes the contribution repository.
     */
    public function __construct(private readonly ContributionRepositoryInterface $contributions) {}

    /**
     * Lists one page of gifts.
     *
     * @return LengthAwarePaginator<int, Contribution>
     */
    public function execute(ListContributionsDTO $dto): LengthAwarePaginator
    {
        return $this->contributions->paginate($dto);
    }
}
