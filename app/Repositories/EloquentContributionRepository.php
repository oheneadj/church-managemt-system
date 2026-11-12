<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Contributions\ListContributionsDTO;
use App\Enums\ContributionFailureReason;
use App\Enums\ContributionStatus;
use App\Enums\PaymentMethod;
use App\Models\Contribution;
use App\Models\FundType;
use App\Models\User;
use App\Scopes\ChurchScope;
use App\ValueObjects\Money;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Eloquent implementation of the contribution queries.
 */
class EloquentContributionRepository implements ContributionRepositoryInterface
{
    /** What every gift shown on screen needs, loaded together so a list is never one query per row. */
    private const DETAIL = ['member', 'service', 'pledge', 'recordedBy', 'voidedBy'];

    /**
     * Total given by a member since a moment (or ever), as an exact decimal string. Voided gifts never count.
     */
    public function totalForMember(string $memberId, ?DateTimeInterface $since): string
    {
        $sum = Contribution::query()
            ->where('member_id', $memberId)
            ->counted()
            ->when($since !== null, fn (Builder $q) => $q->where('contributed_at', '>=', $since))
            ->sum('amount');

        return bcadd((string) $sum, '0', 2);
    }

    /**
     * Total given by a member between two moments, as an exact decimal string. Voided gifts never count.
     *
     * @return numeric-string
     */
    public function totalForMemberBetween(string $memberId, CarbonInterface $from, CarbonInterface $to): string
    {
        return Money::ghs((string) Contribution::query()->where('member_id', $memberId)->counted()->whereBetween('contributed_at', [$from, $to])->sum('amount'))->amount;
    }

    /**
     * Claims the right to queue this gift's receipt. Returns true for the first caller only.
     */
    public function claimReceipt(Contribution $contribution): bool
    {
        return Contribution::query()->whereKey($contribution->getKey())->whereNull('receipt_queued_at')->update(['receipt_queued_at' => now()]) === 1;
    }

    /**
     * Total of the gifts that pay a pledge, as an exact decimal string. Voided gifts never count.
     *
     * @return numeric-string
     */
    public function totalForPledge(string $pledgeId): string
    {
        return bcadd((string) Contribution::query()->where('pledge_id', $pledgeId)->counted()->sum('amount'), '0', 2);
    }

    /**
     * What a service received, added up per fund and payment method, voided gifts left out.
     *
     * @return array<int, array{fund_type_id: string, fund_name: string, payment_method: string, total: numeric-string, gifts: int}>
     */
    public function offeringTotalsForService(string $serviceId): array
    {
        return $this->offeringTotals(Contribution::query()->where('service_id', $serviceId));
    }

    /**
     * The same figures for gifts that have no service and were given between these two moments.
     *
     * @return array<int, array{fund_type_id: string, fund_name: string, payment_method: string, total: numeric-string, gifts: int}>
     */
    public function offeringTotalsWithoutService(CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->offeringTotals($this->withoutServiceBetween($from, $to));
    }

    /**
     * What was given between these two moments, added up for each fund, voided gifts left out.
     */
    public function totalsByFundBetween(CarbonInterface $from, CarbonInterface $to, ?string $fundTypeId = null): array
    {
        $rows = Contribution::query()
            ->counted()
            ->whereBetween('contributed_at', [$from, $to])
            ->when($fundTypeId !== null, fn (Builder $query) => $query->where('fund_type_id', $fundTypeId))
            ->groupBy('fund_type_id')
            ->selectRaw('fund_type_id, SUM(amount) AS total')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[(string) $row->getAttribute('fund_type_id')] = Money::ghs((string) $row->getAttribute('total'))->amount;
        }

        return $totals;
    }

    /**
     * How many gifts at a service have no member, voided gifts left out.
     */
    public function anonymousCountForService(string $serviceId): int
    {
        return Contribution::query()->where('service_id', $serviceId)->counted()->whereNull('member_id')->count();
    }

    /**
     * How many gifts with no service, given between these two moments, have no member, voided gifts left out.
     */
    public function anonymousCountWithoutService(CarbonInterface $from, CarbonInterface $to): int
    {
        return $this->withoutServiceBetween($from, $to)->counted()->whereNull('member_id')->count();
    }

    /**
     * The gifts with no service that were given between these two moments.
     *
     * @return Builder<Contribution>
     */
    private function withoutServiceBetween(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Contribution::query()->whereNull('service_id')->whereBetween('contributed_at', [$from, $to]);
    }

    /**
     * Adds up the gifts in this query that are not voided, per fund and payment method.
     *
     * @param  Builder<Contribution>  $gifts
     * @return array<int, array{fund_type_id: string, fund_name: string, payment_method: string, total: numeric-string, gifts: int}>
     */
    private function offeringTotals(Builder $gifts): array
    {
        $rows = $gifts
            ->counted()
            ->groupBy('fund_type_id', 'fund_name', 'payment_method')
            ->selectRaw('fund_type_id, fund_name, payment_method, SUM(amount) AS total, COUNT(*) AS gifts')
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
     * @return array{fund_type_id: string, fund_name: string, payment_method: string, total: numeric-string, gifts: int}
     */
    private function totalsLine(Contribution $line): array
    {
        return [
            'fund_type_id' => (string) $line->getAttribute('fund_type_id'),
            'fund_name' => (string) $line->getAttribute('fund_name'),
            'payment_method' => $line->payment_method->value,
            'total' => Money::ghs((string) $line->getAttribute('total'))->amount,
            'gifts' => (int) $line->getAttribute('gifts'),
        ];
    }

    /**
     * One page of gifts, newest first. Voided gifts are left out unless asked for.
     *
     * @return LengthAwarePaginator<int, Contribution>
     */
    public function paginate(ListContributionsDTO $filters): LengthAwarePaginator
    {
        return $this->matching($filters)
            ->with(self::DETAIL)
            ->where('is_voided', $filters->isVoided)
            ->orderByDesc('contributed_at')
            ->orderByDesc('id')
            ->paginate($filters->perPage);
    }

    /**
     * Every gift the filters find, voided ones included, oldest first, read a few at a time with the member and the staff
     * member who recorded it (erased people too, who show as deleted), for the CSV export.
     */
    public function forExport(ListContributionsDTO $filters, int $chunkSize): LazyCollection
    {
        return $this->matching($filters)
            ->with(['member' => fn ($member) => $member->withTrashed(), 'recordedBy' => fn ($user) => $user->withTrashed()])
            ->orderBy('contributed_at')
            ->orderBy('id')
            ->lazy($chunkSize);
    }

    /**
     * The gifts of the current church that match the filters (not the voided switch or the order). The list and the export
     * share it, so the file holds what the screen showed.
     *
     * @return Builder<Contribution>
     */
    private function matching(ListContributionsDTO $filters): Builder
    {
        return Contribution::query()
            ->where('status', ContributionStatus::Completed->value)
            ->when($filters->memberId !== null, fn (Builder $q) => $q->where('member_id', $filters->memberId))
            ->when($filters->serviceId !== null, fn (Builder $q) => $q->where('service_id', $filters->serviceId))
            ->when($filters->fundTypeId !== null, fn (Builder $q) => $q->where('fund_type_id', $filters->fundTypeId))
            ->when($filters->paymentMethod !== null, fn (Builder $q) => $q->where('payment_method', $filters->paymentMethod))
            ->when($filters->from !== null, fn (Builder $q) => $q->whereDate('contributed_at', '>=', $filters->from))
            ->when($filters->to !== null, fn (Builder $q) => $q->whereDate('contributed_at', '<=', $filters->to));
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
     * The gift with this id, if there is one.
     */
    public function find(string $id): ?Contribution
    {
        return Contribution::query()->find($id);
    }

    /**
     * The newest mobile money gift still waiting for payment from this phone, for this fund and amount, started since a moment.
     */
    public function findWaitingFor(string $phoneHash, string $fundTypeId, string $amount, DateTimeInterface $since): ?Contribution
    {
        return Contribution::query()
            ->where('payer_phone_hash', $phoneHash)
            ->where('fund_type_id', $fundTypeId)
            ->where('amount', $amount)
            ->where('payment_method', PaymentMethod::Momo->value)
            ->where('status', ContributionStatus::Pending->value)
            ->where('created_at', '>=', $since)
            ->latest('created_at')
            ->first();
    }

    /**
     * The gift with this id, locked until the transaction ends.
     */
    public function lockById(string $id): ?Contribution
    {
        return Contribution::query()->lockForUpdate()->find($id);
    }

    /**
     * The ids of the mobile money gifts still waiting for payment that were started at or before this moment, oldest first.
     *
     * @return Collection<int, string>
     */
    public function pendingDigitalIdsStartedBefore(CarbonInterface $cutoff): Collection
    {
        return Contribution::query()
            ->withoutGlobalScope(ChurchScope::class)
            ->where('payment_method', PaymentMethod::Momo->value)
            ->where('status', ContributionStatus::Pending->value)
            ->where('created_at', '<=', $cutoff)
            ->orderBy('created_at')
            ->pluck('id');
    }

    /**
     * The ids of the mobile money gifts that expired unpaid and were started at or after this moment, oldest first.
     *
     * @return Collection<int, string>
     */
    public function expiredDigitalIdsStartedSince(CarbonInterface $since): Collection
    {
        return Contribution::query()
            ->withoutGlobalScope(ChurchScope::class)
            ->where('payment_method', PaymentMethod::Momo->value)
            ->where('status', ContributionStatus::Failed->value)
            ->where('failure_reason', ContributionFailureReason::Expired->value)
            ->where('created_at', '>=', $since)
            ->orderBy('created_at')
            ->pluck('id');
    }

    /**
     * The ids of the mobile money gifts that completed at or before this moment and have no payout record at all, in every church.
     *
     * @return Collection<int, string>
     */
    public function completedDigitalIdsWithoutPayout(CarbonInterface $before): Collection
    {
        return Contribution::query()
            ->withoutGlobalScope(ChurchScope::class)
            ->where('payment_method', PaymentMethod::Momo->value)
            ->where('status', ContributionStatus::Completed->value)
            ->where('updated_at', '<=', $before)
            ->whereNotIn('id', fn ($payouts) => $payouts->select('contribution_id')->from('disbursements')->whereNotNull('contribution_id'))
            ->orderBy('updated_at')
            ->pluck('id');
    }

    /**
     * Saves a change to a gift.
     */
    public function save(Contribution $contribution): Contribution
    {
        $contribution->save();

        return $contribution;
    }

    /**
     * Fills the fund name on gifts that have none, from the fund's current name, a batch at a time, in every church.
     */
    public function backfillFundNames(int $chunk): int
    {
        $filled = 0;

        Contribution::query()->withoutGlobalScope(ChurchScope::class)->whereNull('fund_name')->chunkById($chunk, function ($gifts) use (&$filled): void {
            $names = FundType::query()->withoutGlobalScope(ChurchScope::class)->withTrashed()->whereKey($gifts->pluck('fund_type_id')->unique()->all())->pluck('name', 'id');

            foreach ($gifts as $gift) {
                $gift->update(['fund_name' => $names[$gift->fund_type_id]]);
                $filled++;
            }
        });

        return $filled;
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
