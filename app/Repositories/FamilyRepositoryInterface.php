<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Families\ListFamiliesDTO;
use App\Models\Family;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Every query on families lives behind this contract.
 */
interface FamilyRepositoryInterface
{
    /**
     * One page of families. The member count only counts members the viewer may see.
     *
     * @return LengthAwarePaginator<int, Family>
     */
    public function paginate(ListFamiliesDTO $filters): LengthAwarePaginator;

    /**
     * Creates a family.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Family;

    /**
     * Changes a family.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Family $family, array $attributes): Family;

    /**
     * Loads the head and the members the viewer may see.
     */
    public function loadWithVisibleMembers(Family $family, User $viewer): Family;

    /**
     * Locks the family row until the transaction ends, so two requests cannot both take the head role.
     */
    public function lock(string $familyId): Family;

    /**
     * Records the member as the family's head.
     */
    public function setHead(string $familyId, string $memberId): void;

    /**
     * Reloads the family's own columns, for example after its head was set by a separate update.
     */
    public function fresh(Family $family): Family;
}
