<?php

declare(strict_types=1);

namespace App\Actions\FundTypes;

use App\DTOs\FundTypes\UpdateFundTypeDTO;
use App\Models\FundType;
use App\Repositories\FundTypeRepositoryInterface;

/**
 * Edits a fund type, or switches it off by setting is_active to false. Past gifts keep pointing at it.
 */
final class UpdateFundTypeAction
{
    /**
     * Takes the fund type repository.
     */
    public function __construct(private readonly FundTypeRepositoryInterface $fundTypes) {}

    /**
     * Changes the fund type.
     */
    public function execute(UpdateFundTypeDTO $dto): FundType
    {
        return $this->fundTypes->update($dto->fundType, $dto->attributes);
    }
}
