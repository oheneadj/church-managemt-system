<?php

declare(strict_types=1);

namespace App\Actions\FundTypes;

use App\DTOs\FundTypes\CreateFundTypeDTO;
use App\Models\FundType;
use App\Repositories\FundTypeRepositoryInterface;

/**
 * Adds a fund type to the staff member's church.
 */
final class CreateFundTypeAction
{
    /**
     * Takes the fund type repository.
     */
    public function __construct(private readonly FundTypeRepositoryInterface $fundTypes) {}

    /**
     * Adds the fund type.
     */
    public function execute(CreateFundTypeDTO $dto): FundType
    {
        return $this->fundTypes->create([
            'church_id' => $dto->actor->church_id,
            'name' => $dto->name,
            'description' => $dto->description,
            'sort_order' => $dto->sortOrder,
            'is_active' => $dto->isActive,
        ]);
    }
}
