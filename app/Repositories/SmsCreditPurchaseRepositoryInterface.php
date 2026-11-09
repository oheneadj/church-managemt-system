<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\SmsCreditPurchase;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Every query on SMS credit purchases lives behind this contract. These are read by workers that have no church in the request,
 * so none of them is limited to a church.
 */
interface SmsCreditPurchaseRepositoryInterface
{
    /**
     * Records a new purchase.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): SmsCreditPurchase;

    /**
     * The purchase with this id, if there is one.
     */
    public function find(string $id): ?SmsCreditPurchase;

    /**
     * The purchase with this id, locked until the transaction ends, so two checks of one payment take turns.
     */
    public function lock(string $id): ?SmsCreditPurchase;

    /**
     * Saves a changed purchase.
     */
    public function save(SmsCreditPurchase $purchase): void;

    /**
     * The ids of the purchases still waiting on the provider that were started before this time, oldest first.
     *
     * @return Collection<int, string>
     */
    public function pendingIdsStartedBefore(CarbonInterface $cutoff): Collection;
}
