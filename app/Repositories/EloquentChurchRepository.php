<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Church;

/**
 * Eloquent implementation of the church queries.
 */
class EloquentChurchRepository implements ChurchRepositoryInterface
{
    /**
     * Locks the church row until the transaction ends.
     */
    public function lock(string $churchId): Church
    {
        return Church::query()->whereKey($churchId)->lockForUpdate()->firstOrFail();
    }

    /**
     * The church with this subdomain slug, if any.
     */
    public function findBySlug(string $slug): ?Church
    {
        return Church::query()->where('slug', $slug)->first();
    }

    /**
     * The church with this custom domain, if any.
     */
    public function findByCustomDomain(string $domain): ?Church
    {
        return Church::query()->where('custom_domain', $domain)->first();
    }

    /**
     * Moves the member number counter forward by one.
     */
    public function incrementMemberNumberSequence(Church $church): Church
    {
        $church->increment('member_number_seq');

        return $church;
    }
}
