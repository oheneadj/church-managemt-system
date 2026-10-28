<?php

declare(strict_types=1);

namespace App\Actions\Families;

use App\DTOs\Families\ShowFamilyDTO;
use App\Models\Family;
use App\Repositories\FamilyRepositoryInterface;

/**
 * Loads one family with its head and the members the viewer may see.
 */
final class ShowFamilyAction
{
    /**
     * Takes the family repository.
     */
    public function __construct(private readonly FamilyRepositoryInterface $families) {}

    /**
     * Loads one family with its head and the members the viewer may see.
     */
    public function execute(ShowFamilyDTO $dto): Family
    {
        return $this->families->loadWithVisibleMembers($dto->family, $dto->viewer);
    }
}
