<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Contributions\ListContributionsDTO;
use App\Models\Contribution;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
     * Total of the gifts that pay a pledge, as an exact decimal string. Voided gifts never count.
     *
     * @return numeric-string
     */
    public function totalForPledge(string $pledgeId): string;

    /**
     * What a service received, added up per fund and payment method, voided gifts left out. Each line is the fund id,
     * the payment method, the total as an exact decimal string and how many gifts it adds up.
     *
     * @return array<int, array{fund_type_id: string, payment_method: string, total: numeric-string, gifts: int}>
     */
    public function offeringTotalsForService(string $serviceId): array;

    /**
     * How many gifts at a service have no member, voided gifts left out.
     */
    public function anonymousCountForService(string $serviceId): int;

    /**
     * One page of gifts, newest first, with what the list shows already loaded. Voided gifts are left out unless asked for.
     *
     * @return LengthAwarePaginator<int, Contribution>
     */
    public function paginate(ListContributionsDTO $filters): LengthAwarePaginator;

    /**
     * Records a gift and loads what the answer shows.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Contribution;

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
