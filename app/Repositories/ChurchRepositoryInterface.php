<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Church;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Every query the member code needs on churches lives behind this contract.
 */
interface ChurchRepositoryInterface
{
    /**
     * Locks the church row until the transaction ends, so the member number counter moves one request at a time.
     */
    public function lock(string $churchId): Church;

    /**
     * Moves the member number counter forward by one.
     */
    public function incrementMemberNumberSequence(Church $church): Church;

    /**
     * The church with this subdomain slug, if any.
     */
    public function findBySlug(string $slug): ?Church;

    /**
     * The church with this custom domain, if any.
     */
    public function findByCustomDomain(string $domain): ?Church;

    /**
     * Whether a church, including a deleted one, already holds this subdomain slug.
     */
    public function slugExists(string $slug): bool;

    /**
     * Adds a church.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Church;

    /**
     * Saves a changed church.
     */
    public function save(Church $church): Church;

    /**
     * Soft-deletes the church. The row stays, so records that point to it keep working.
     */
    public function delete(Church $church): void;

    /**
     * The churches that cancelled on or before this moment and have not been purged yet.
     *
     * @return Collection<int, Church>
     */
    public function cancelledBefore(CarbonInterface $cutoff): Collection;
}
