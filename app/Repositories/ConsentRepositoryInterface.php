<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ConsentPurpose;
use App\Models\Consent;
use App\Models\Member;
use Illuminate\Database\Eloquent\Collection;

/**
 * Every query on consent records lives behind this contract.
 */
interface ConsentRepositoryInterface
{
    /**
     * The member's agreement for this purpose that has not been withdrawn, if there is one.
     */
    public function openFor(Member $member, ConsentPurpose $purpose): ?Consent;

    /**
     * Records a new agreement.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Consent;

    /**
     * Marks an agreement as withdrawn now.
     */
    public function withdraw(Consent $consent): void;

    /**
     * Every agreement the member has made, oldest first, including withdrawn ones.
     *
     * @return Collection<int, Consent>
     */
    public function historyFor(Member $member): Collection;
}
