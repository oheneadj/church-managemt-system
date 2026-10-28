<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Contributions\ListContributionsDTO;
use App\Models\Contribution;
use App\Models\User;
use App\ValueObjects\Money;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent implementation of the contribution queries.
 */
class EloquentContributionRepository implements ContributionRepositoryInterface
{
    /** What every gift shown on screen needs, loaded together so a list is never one query per row. */
    private const DETAIL = ['member', 'fundType', 'service', 'pledge', 'recordedBy', 'voidedBy'];

    /**
     * Total given by a member since a moment (or ever), as an exact decimal string. Voided gifts never count.
     */
    public function totalForMember(string $memberId, ?DateTimeInterface $since): string
    {
        $sum = Contribution::query()
            ->where('member_id', $memberId)
            ->where('is_voided', false)
            ->when($since !== null, fn (Builder $q) => $q->where('contributed_at', '>=', $since))
            ->sum('amount');

        return bcadd((string) $sum, '0', 2);
    }

    /**
     * Total of the gifts that pay a pledge, as an exact decimal string. Voided gifts never count.
     *
     * @return numeric-string
     */
    public function totalForPledge(string $pledgeId): string
    {
        return bcadd((string) Contribution::query()->where('pledge_id', $pledgeId)->where('is_voided', false)->sum('amount'), '0', 2);
    }

    /**
     * What a service received, added up per fund and payment method, voided gifts left out.
     *
     * @return array<int, array{fund_type_id: string, payment_method: string, total: numeric-string, gifts: int}>
     */
    public function offeringTotalsForService(string $serviceId): array
    {
        $rows = Contribution::query()
            ->where('service_id', $serviceId)
            ->where('is_voided', false)
            ->groupBy('fund_type_id', 'payment_method')
            ->selectRaw('fund_type_id, payment_method, SUM(amount) AS total, COUNT(*) AS gifts')
            ->get();

        $lines = [];

        foreach ($rows as $row) {
            $lines[] = $this->totalsLine($row);
        }

        return $lines;
    }

    /**
     * One line of the offering totals, with the total as an exact decimal string.
     *
     * @return array{fund_type_id: string, payment_method: string, total: numeric-string, gifts: int}
     */
    private function totalsLine(Contribution $line): array
    {
        return [
            'fund_type_id' => (string) $line->getAttribute('fund_type_id'),
            'payment_method' => $line->payment_method->value,
            'total' => Money::ghs((string) $line->getAttribute('total'))->amount,
            'gifts' => (int) $line->getAttribute('gifts'),
        ];
    }

    /**
     * How many gifts at a service have no member, voided gifts left out.
     */
    public function anonymousCountForService(string $serviceId): int
    {
        return Contribution::query()->where('service_id', $serviceId)->where('is_voided', false)->whereNull('member_id')->count();
    }

    /**
     * One page of gifts, newest first. Voided gifts are left out unless asked for.
     *
     * @return LengthAwarePaginator<int, Contribution>
     */
    public function paginate(ListContributionsDTO $filters): LengthAwarePaginator
    {
        return Contribution::query()
            ->with(self::DETAIL)
            ->where('is_voided', $filters->isVoided)
            ->when($filters->memberId !== null, fn (Builder $q) => $q->where('member_id', $filters->memberId))
            ->when($filters->serviceId !== null, fn (Builder $q) => $q->where('service_id', $filters->serviceId))
            ->when($filters->fundTypeId !== null, fn (Builder $q) => $q->where('fund_type_id', $filters->fundTypeId))
            ->when($filters->paymentMethod !== null, fn (Builder $q) => $q->where('payment_method', $filters->paymentMethod))
            ->when($filters->from !== null, fn (Builder $q) => $q->whereDate('contributed_at', '>=', $filters->from))
            ->when($filters->to !== null, fn (Builder $q) => $q->whereDate('contributed_at', '<=', $filters->to))
            ->orderByDesc('contributed_at')
            ->orderByDesc('id')
            ->paginate($filters->perPage);
    }

    /**
     * Records a gift and loads what the answer shows.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Contribution
    {
        return $this->withDetail(Contribution::query()->create($attributes));
    }

    /**
     * Loads what a single gift shows.
     */
    public function withDetail(Contribution $contribution): Contribution
    {
        return $contribution->load(self::DETAIL);
    }

    /**
     * Reads the gift again with its row locked until the transaction ends.
     */
    public function lockForUpdate(Contribution $contribution): Contribution
    {
        return Contribution::query()->lockForUpdate()->whereKey($contribution->getKey())->firstOrFail();
    }

    /**
     * Marks the gift as voided by this person, now, with the reason. The row stays.
     */
    public function markVoided(Contribution $contribution, User $by, string $reason, DateTimeInterface $at): Contribution
    {
        $contribution->update(['is_voided' => true, 'voided_at' => $at, 'voided_by' => $by->getKey(), 'void_reason' => $reason]);

        return $this->withDetail($contribution);
    }
}
