<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Pledges\ListPledgesDTO;
use App\Enums\PledgeStatus;
use App\Models\Pledge;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Every query on pledges lives behind this contract.
 */
interface PledgeRepositoryInterface
{
    /**
     * One page of pledges, those due soonest first, with the member and fund loaded.
     *
     * @return LengthAwarePaginator<int, Pledge>
     */
    public function paginate(ListPledgesDTO $filters): LengthAwarePaginator;

    /**
     * The church's pledge with this id, if there is one.
     */
    public function find(string $id): ?Pledge;

    /**
     * Adds a pledge and loads what the answer shows.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Pledge;

    /**
     * Changes a pledge's own fields.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Pledge $pledge, array $attributes): Pledge;

    /**
     * Loads the member and fund, and (when asked) every gift that pays the pledge, voided ones included and marked.
     */
    public function withDetail(Pledge $pledge, bool $withContributions): Pledge;

    /**
     * Reads the pledge again with its row locked until the transaction ends, so gifts and voids against it take turns.
     */
    public function lockForUpdate(string $pledgeId): Pledge;

    /**
     * Saves the paid amount and status the progress service worked out.
     *
     * @param  numeric-string  $amountPaid
     */
    public function saveProgress(Pledge $pledge, string $amountPaid, PledgeStatus $status): Pledge;

    /**
     * Hands every active pledge whose target date is before this day to the callback, a few at a time, across all
     * churches (the daily job). This deliberately looks past the church scope; the callback must set the pledge's
     * church in context before touching anything else.
     *
     * @param  Closure(Pledge): void  $callback
     */
    public function eachActivePastDate(CarbonInterface $day, Closure $callback): void;
}
