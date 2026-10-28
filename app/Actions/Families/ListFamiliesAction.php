<?php

declare(strict_types=1);

namespace App\Actions\Families;

use App\DTOs\Families\ListFamiliesDTO;
use App\Models\Family;
use App\Repositories\FamilyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lists one page of families. The member counts only include members the viewer may see.
 */
final class ListFamiliesAction
{
    public function __construct(private readonly FamilyRepositoryInterface $families) {}

    /**
     * @return LengthAwarePaginator<int, Family>
     */
    public function execute(ListFamiliesDTO $dto): LengthAwarePaginator
    {
        return $this->families->paginate($dto);
    }
}
