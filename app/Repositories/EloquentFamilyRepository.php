<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Families\ListFamiliesDTO;
use App\Models\Family;
use App\Models\Member;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent implementation of the family queries.
 */
class EloquentFamilyRepository implements FamilyRepositoryInterface
{
    public function paginate(ListFamiliesDTO $filters): LengthAwarePaginator
    {
        return Family::query()
            ->withCount(['members' => function (Builder $members) use ($filters): void {
                /** @var Builder<Member> $members */
                $members->visibleTo($filters->viewer);
            }])
            ->when($filters->search !== null, fn (Builder $q) => $q->whereLike('name', '%'.addcslashes((string) $filters->search, '%_\\').'%'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($filters->perPage);
    }

    public function create(array $attributes): Family
    {
        return Family::query()->create($attributes)->refresh();
    }

    public function update(Family $family, array $attributes): Family
    {
        $family->update($attributes);

        return $family;
    }

    public function loadWithVisibleMembers(Family $family, User $viewer): Family
    {
        $family->setRelation('members', $family->members()->visibleTo($viewer)->orderBy('first_name')->orderBy('id')->get());

        return $family->load('head');
    }

    public function lock(string $familyId): Family
    {
        return Family::query()->whereKey($familyId)->lockForUpdate()->firstOrFail();
    }

    public function fresh(Family $family): Family
    {
        return $family->refresh();
    }

    public function setHead(string $familyId, string $memberId): void
    {
        Family::query()->whereKey($familyId)->update(['head_member_id' => $memberId]);
    }
}
