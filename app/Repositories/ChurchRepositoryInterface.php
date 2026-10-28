<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Church;

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
}
