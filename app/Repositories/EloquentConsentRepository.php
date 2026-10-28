<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ConsentPurpose;
use App\Models\Consent;
use App\Models\Member;
use Illuminate\Database\Eloquent\Collection;

/**
 * Eloquent implementation of the consent queries.
 */
class EloquentConsentRepository implements ConsentRepositoryInterface
{
    /**
     * The member's agreement for this purpose that has not been withdrawn, if there is one.
     */
    public function openFor(Member $member, ConsentPurpose $purpose): ?Consent
    {
        return Consent::query()->where('member_id', $member->id)->where('purpose', $purpose)->whereNull('withdrawn_at')->first();
    }

    /**
     * Records a new agreement.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Consent
    {
        return Consent::query()->create($attributes);
    }

    /**
     * Marks an agreement as withdrawn now.
     */
    public function withdraw(Consent $consent): void
    {
        $consent->update(['withdrawn_at' => now()]);
    }

    /**
     * Every agreement the member has made, oldest first, including withdrawn ones.
     *
     * @return Collection<int, Consent>
     */
    public function historyFor(Member $member): Collection
    {
        return Consent::query()->where('member_id', $member->id)->orderBy('consented_at')->orderBy('id')->get();
    }
}
