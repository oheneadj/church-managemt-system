<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\SmsPackage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent implementation of the SMS credit package queries.
 */
class EloquentSmsPackageRepository implements SmsPackageRepositoryInterface
{
    /**
     * One page of every package, in the order churches see them.
     */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return SmsPackage::query()->orderBy('sort_order')->orderBy('name')->orderBy('id')->paginate($perPage);
    }

    /**
     * Whether a package already has this name, whatever its capitals, apart from the one given.
     */
    public function nameTaken(string $name, ?string $exceptPackageId = null): bool
    {
        return SmsPackage::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower(trim($name))])
            ->when($exceptPackageId !== null, fn (Builder $query) => $query->whereKeyNot($exceptPackageId))
            ->exists();
    }

    /**
     * Creates a package and reloads its database defaults.
     */
    public function create(array $attributes): SmsPackage
    {
        return SmsPackage::query()->create($attributes)->refresh();
    }

    /**
     * Changes a package.
     */
    public function update(SmsPackage $package, array $attributes): SmsPackage
    {
        $package->update($attributes);

        return $package;
    }
}
