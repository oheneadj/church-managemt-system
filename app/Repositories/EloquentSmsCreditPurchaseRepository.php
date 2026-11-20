<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\SmsCreditPurchaseStatus;
use App\Models\SmsCreditPurchase;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Eloquent implementation of the SMS credit purchase queries.
 */
class EloquentSmsCreditPurchaseRepository implements SmsCreditPurchaseRepositoryInterface
{
    /**
     * Records a new purchase.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): SmsCreditPurchase
    {
        return SmsCreditPurchase::query()->create($attributes)->refresh();
    }

    /**
     * The purchase with this id, if there is one.
     */
    public function find(string $id): ?SmsCreditPurchase
    {
        return SmsCreditPurchase::query()->find($id);
    }

    /**
     * The purchase with this id, locked until the transaction ends.
     */
    public function lock(string $id): ?SmsCreditPurchase
    {
        return SmsCreditPurchase::query()->lockForUpdate()->find($id);
    }

    /**
     * Saves a changed purchase.
     */
    public function save(SmsCreditPurchase $purchase): void
    {
        $purchase->save();
    }

    /**
     * The ids of the purchases still waiting on the provider that were started before this time, oldest first.
     *
     * @return Collection<int, string>
     */
    public function pendingIdsStartedBefore(CarbonInterface $cutoff): Collection
    {
        return SmsCreditPurchase::query()
            ->where('status', SmsCreditPurchaseStatus::Pending)
            ->where('created_at', '<=', $cutoff)
            ->orderBy('created_at')
            ->pluck('id');
    }
}
