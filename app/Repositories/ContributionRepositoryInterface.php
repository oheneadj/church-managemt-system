<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Contributions\ListContributionsDTO;
use App\Models\Contribution;
use App\Models\User;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Every query on contributions lives behind this contract.
 */
interface ContributionRepositoryInterface
{
    /**
     * Total given by a member since a moment (or ever), as an exact decimal string. Voided gifts never count.
     */
    public function totalForMember(string $memberId, ?DateTimeInterface $since): string;

    /**
     * Total given by a member between two moments, as an exact decimal string. Voided gifts never count.
     *
     * @return numeric-string
     */
    public function totalForMemberBetween(string $memberId, CarbonInterface $from, CarbonInterface $to): string;

    /**
     * Claims the right to queue this gift's receipt. Returns true for the first caller only: it sets the time in one
     * statement that finds the gift with no time set, so two callers at once cannot both win (agreement section 11).
     */
    public function claimReceipt(Contribution $contribution): bool;

    /**
     * Total of the gifts that pay a pledge, as an exact decimal string. Voided gifts never count.
     *
     * @return numeric-string
     */
    public function totalForPledge(string $pledgeId): string;

    /**
     * What a service received, added up per fund (by the name it had when the gift was recorded) and payment method,
     * voided gifts left out. Each line is the fund id, the fund name, the payment method, the total as an exact
     * decimal string and how many gifts it adds up.
     *
     * @return array<int, array{fund_type_id: string, fund_name: string, payment_method: string, total: numeric-string, gifts: int}>
     */
    public function offeringTotalsForService(string $serviceId): array;

    /**
     * The same figures for gifts that have no service and were given between these two moments (a church day),
     * voided gifts left out.
     *
     * @return array<int, array{fund_type_id: string, fund_name: string, payment_method: string, total: numeric-string, gifts: int}>
     */
    public function offeringTotalsWithoutService(CarbonInterface $from, CarbonInterface $to): array;

    /**
     * What was given between these two moments (both included), added up for each fund, voided gifts left out, keyed by fund
     * id. A fund with no gifts has no entry. Narrowed to one fund when an id is given.
     *
     * @return array<string, numeric-string>
     */
    public function totalsByFundBetween(CarbonInterface $from, CarbonInterface $to, ?string $fundTypeId = null): array;

    /**
     * How many gifts at a service have no member, voided gifts left out.
     */
    public function anonymousCountForService(string $serviceId): int;

    /**
     * How many gifts with no service, given between these two moments, have no member, voided gifts left out.
     */
    public function anonymousCountWithoutService(CarbonInterface $from, CarbonInterface $to): int;

    /**
     * One page of gifts, newest first, with what the list shows already loaded. Voided gifts are left out unless asked for.
     *
     * @return LengthAwarePaginator<int, Contribution>
     */
    public function paginate(ListContributionsDTO $filters): LengthAwarePaginator;

    /**
     * Every gift the filters find, voided ones included, oldest first, read a few at a time, for the CSV export.
     *
     * @return LazyCollection<int, Contribution>
     */
    public function forExport(ListContributionsDTO $filters, int $chunkSize): LazyCollection;

    /**
     * Records a gift and loads what the answer shows.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Contribution;

    /**
     * The gift with this id, if there is one. The queued payment jobs use it: no church is bound there, so it finds the gift
     * wherever it is.
     */
    public function find(string $id): ?Contribution;

    /**
     * The newest mobile money gift of this church that is still waiting for payment, from the phone with this hash, for this fund
     * and amount, started since the given moment. A person who taps Pay again, or whose prompt never arrived, gets this one back.
     */
    public function findWaitingFor(string $phoneHash, string $fundTypeId, string $amount, DateTimeInterface $since): ?Contribution;

    /**
     * The gift with this id, locked until the transaction ends, or null when there is none. Settling uses it, so a webhook and a
     * check that arrive together take turns.
     */
    public function lockById(string $id): ?Contribution;

    /**
     * The ids of the mobile money gifts still waiting for payment that were started at or before this moment, oldest first.
     *
     * @return Collection<int, string>
     */
    public function pendingDigitalIdsStartedBefore(CarbonInterface $cutoff): Collection;

    /**
     * The ids of the mobile money gifts that expired unpaid and were started at or after this moment, oldest first: the ones a late
     * approval could still reach.
     *
     * @return Collection<int, string>
     */
    public function expiredDigitalIdsStartedSince(CarbonInterface $since): Collection;

    /**
     * The ids of the mobile money gifts that completed at or before this moment and have no payout record at all, in every church. There
     * should never be any: completing a gift records the payout it is owed (D-069 step e1).
     *
     * @return Collection<int, string>
     */
    public function completedDigitalIdsWithoutPayout(CarbonInterface $before): Collection;

    /**
     * Saves a change to a gift.
     */
    public function save(Contribution $contribution): Contribution;

    /**
     * Fills the fund name on gifts that have none, from the fund's current name, a batch at a time, in every church
     * (a data migration, D-048). Returns how many gifts were filled. This deliberately looks past the church scope.
     */
    public function backfillFundNames(int $chunk): int;

    /**
     * Loads what a single gift shows (member, fund, service, who recorded and who voided it).
     */
    public function withDetail(Contribution $contribution): Contribution;

    /**
     * Reads the gift again with its row locked until the transaction ends, so two voids at once cannot both pass the check.
     */
    public function lockForUpdate(Contribution $contribution): Contribution;

    /**
     * Marks the gift as voided by this person, now, with the reason. The row stays.
     */
    public function markVoided(Contribution $contribution, User $by, string $reason, DateTimeInterface $at): Contribution;
}
