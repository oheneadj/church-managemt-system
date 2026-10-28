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

    public function incrementMemberNumberSequence(Church $church): Church;

    public function findBySlug(string $slug): ?Church;

    public function findByCustomDomain(string $domain): ?Church;
}
