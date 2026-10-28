<?php

declare(strict_types=1);

namespace App\Actions\Families;

use App\DTOs\Families\UpdateFamilyDTO;
use App\Models\Family;
use App\Repositories\FamilyRepositoryInterface;

/**
 * Renames a family or edits its notes. The head is changed through the members' roles, never here.
 */
final class UpdateFamilyAction
{
    /**
     * Takes the family repository.
     */
    public function __construct(private readonly FamilyRepositoryInterface $families) {}

    /**
     * Renames a family or edits its notes.
     */
    public function execute(UpdateFamilyDTO $dto): Family
    {
        $family = $this->families->update($dto->family, $dto->attributes);

        return $this->families->loadWithVisibleMembers($family, $dto->actor);
    }
}
