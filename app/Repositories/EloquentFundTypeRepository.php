<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\FundTypes\ListFundTypesDTO;
use App\Models\FundType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Eloquent implementation of the fund type queries.
 */
class EloquentFundTypeRepository implements FundTypeRepositoryInterface
{
    /**
     * One page of the church's fund types in display order.
     *
     * @return LengthAwarePaginator<int, FundType>
     */
    public function paginate(ListFundTypesDTO $filters): LengthAwarePaginator
    {
        return FundType::query()
            ->when($filters->isActive !== null, fn (Builder $q) => $q->where('is_active', $filters->isActive))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($filters->perPage);
    }

    /**
     * Adds a fund type.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): FundType
    {
        return FundType::query()->create($attributes);
    }

    /**
     * Changes a fund type.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(FundType $fundType, array $attributes): FundType
    {
        $fundType->update($attributes);

        return $fundType;
    }

    /**
     * Whether the church already has a fund type with this name, ignoring capitals. The fund being changed can be left out.
     */
    public function nameTaken(string $name, ?string $exceptFundTypeId = null): bool
    {
        // The same expression as the unique index, with the name as a bound value, so the check and the index agree.
        return FundType::query()
            ->whereRaw('lower(name) = lower(?)', [$name])
            ->when($exceptFundTypeId !== null, fn (Builder $q) => $q->whereKeyNot($exceptFundTypeId))
            ->exists();
    }

    /**
     * The church's fund types with these ids, ordered for display.
     *
     * @param  list<string>  $ids
     * @return Collection<int, FundType>
     */
    public function findMany(array $ids): Collection
    {
        return FundType::query()->whereKey($ids)->orderBy('sort_order')->orderBy('name')->orderBy('id')->get();
    }

    /**
     * Adds several fund types in one go, each with the church id already in its attributes.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function createMany(array $rows): void
    {
        foreach ($rows as $row) {
            FundType::query()->create($row);
        }
    }
}
