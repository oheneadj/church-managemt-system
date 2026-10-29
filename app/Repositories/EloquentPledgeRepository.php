<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Pledges\ListPledgesDTO;
use App\Enums\PledgeStatus;
use App\Models\Contribution;
use App\Models\Pledge;
use App\Scopes\ChurchScope;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent implementation of the pledge queries.
 */
class EloquentPledgeRepository implements PledgeRepositoryInterface
{
    /**
     * One page of pledges, those due soonest first, with the member and fund loaded.
     *
     * @return LengthAwarePaginator<int, Pledge>
     */
    public function paginate(ListPledgesDTO $filters): LengthAwarePaginator
    {
        return Pledge::query()
            ->with(['member', 'fundType'])
            ->when($filters->memberId !== null, fn (Builder $q) => $q->where('member_id', $filters->memberId))
            ->when($filters->status !== null, fn (Builder $q) => $q->where('status', $filters->status))
            ->when($filters->fundTypeId !== null, fn (Builder $q) => $q->where('fund_type_id', $filters->fundTypeId))
            ->orderBy('target_date')
            ->orderBy('id')
            ->paginate($filters->perPage);
    }

    /**
     * The church's pledge with this id, if there is one.
     */
    public function find(string $id): ?Pledge
    {
        return Pledge::query()->find($id);
    }

    /**
     * Adds a pledge and loads what the answer shows.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Pledge
    {
        return $this->withDetail(Pledge::query()->create($attributes), false);
    }

    /**
     * Changes a pledge's own fields.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Pledge $pledge, array $attributes): Pledge
    {
        $pledge->update($attributes);

        return $pledge;
    }

    /**
     * Loads the member and fund, and (when asked) every gift that pays the pledge.
     */
    public function withDetail(Pledge $pledge, bool $withContributions): Pledge
    {
        $pledge->load(['member', 'fundType']);

        if ($withContributions) {
            $pledge->load(['contributions' => function (HasMany $gifts): void {
                /** @var HasMany<Contribution, Pledge> $gifts */
                $gifts->with(['member', 'service', 'recordedBy', 'voidedBy'])->orderByDesc('contributed_at')->orderByDesc('id');
            }]);
        }

        return $pledge;
    }

    /**
     * Reads the pledge again with its row locked until the transaction ends.
     */
    public function lockForUpdate(string $pledgeId): Pledge
    {
        return Pledge::query()->lockForUpdate()->whereKey($pledgeId)->firstOrFail();
    }

    /**
     * Saves the paid amount and status the progress service worked out.
     *
     * @param  numeric-string  $amountPaid
     */
    public function saveProgress(Pledge $pledge, string $amountPaid, PledgeStatus $status): Pledge
    {
        $pledge->update(['amount_paid' => $amountPaid, 'status' => $status]);

        return $pledge;
    }

    /**
     * Hands every active pledge whose target date is before this day to the callback, a few at a time.
     *
     * @param  Closure(Pledge): void  $callback
     */
    public function eachActivePastDate(CarbonInterface $day, Closure $callback): void
    {
        Pledge::query()
            ->withoutGlobalScope(ChurchScope::class)
            ->with('church')
            ->where('status', PledgeStatus::Active)
            ->whereDate('target_date', '<', $day)
            ->chunkById(config('giving.pledge_refresh_chunk'), function ($pledges) use ($callback): void {
                foreach ($pledges as $pledge) {
                    $callback($pledge);
                }
            });
    }
}
