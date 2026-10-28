<?php

declare(strict_types=1);

namespace App\Actions\FundTypes;

use App\DTOs\FundTypes\ListFundTypesDTO;
use App\Models\FundType;
use App\Repositories\FundTypeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lists one page of the church's fund types.
 */
final class ListFundTypesAction
{
    /**
     * Takes the fund type repository.
     */
    public function __construct(private readonly FundTypeRepositoryInterface $fundTypes) {}

    /**
     * Lists one page of fund types.
     *
     * @return LengthAwarePaginator<int, FundType>
     */
    public function execute(ListFundTypesDTO $dto): LengthAwarePaginator
    {
        return $this->fundTypes->paginate($dto);
    }
}
