<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\SmsPackage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Every query on SMS credit packages lives behind this contract (D-069).
 */
interface SmsPackageRepositoryInterface
{
    /**
     * One page of every package, switched off ones too, in the order churches see them: place in the list, then name.
     *
     * @return LengthAwarePaginator<int, SmsPackage>
     */
    public function paginate(int $perPage): LengthAwarePaginator;

    /**
     * Every package a church may buy (the ones switched on), in the order churches see them: place in the list, then name.
     *
     * @return Collection<int, SmsPackage>
     */
    public function active(): Collection;

    /**
     * The package with this id, only if it is switched on.
     */
    public function findActive(string $id): ?SmsPackage;

    /**
     * Whether a package already has this name, whatever its capitals, apart from the one given.
     */
    public function nameTaken(string $name, ?string $exceptPackageId = null): bool;

    /**
     * Creates a package and reloads its database defaults.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): SmsPackage;

    /**
     * Changes a package.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(SmsPackage $package, array $attributes): SmsPackage;
}
