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
     * Whether a church, including a deleted one, already holds this subdomain slug. A deleted church still owns
     * its row, and the unique index covers it.
     */
    public function slugExists(string $slug): bool
    {
        return Church::query()->withTrashed()->where('slug', $slug)->exists();
    }

    /**
     * Adds a church.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Church
    {
        return Church::query()->create($attributes);
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
