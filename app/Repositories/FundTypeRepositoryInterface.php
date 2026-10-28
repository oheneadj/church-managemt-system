<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\FundTypes\ListFundTypesDTO;
use App\Models\FundType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Every query on fund types lives behind this contract.
 */
interface FundTypeRepositoryInterface
{
    /**
     * One page of the church's fund types in display order.
     *
     * @return LengthAwarePaginator<int, FundType>
     */
    public function paginate(ListFundTypesDTO $filters): LengthAwarePaginator;

    /**
     * Adds a fund type.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): FundType;

    /**
     * Changes a fund type.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(FundType $fundType, array $attributes): FundType;

    /**
     * Whether the church already has a fund type with this name, ignoring capitals. The fund being changed can be left out.
     */
    public function nameTaken(string $name, ?string $exceptFundTypeId = null): bool;

    /**
     * Adds several fund types in one go, each with the church id already in its attributes.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function createMany(array $rows): void;
}
