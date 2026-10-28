<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Church;

/**
 * Eloquent implementation of the church queries.
 */
class EloquentChurchRepository implements ChurchRepositoryInterface
{
    public function lock(string $churchId): Church
    {
        return Church::query()->whereKey($churchId)->lockForUpdate()->firstOrFail();
    }

    public function findBySlug(string $slug): ?Church
    {
        return Church::query()->where('slug', $slug)->first();
    }

    public function findByCustomDomain(string $domain): ?Church
    {
        return Church::query()->where('custom_domain', $domain)->first();
    }

    public function incrementMemberNumberSequence(Church $church): Church
    {
        $church->increment('member_number_seq');

        return $church;
    }
}
