<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MemberNumberPrefixMissingException;
use App\Repositories\ChurchRepositoryInterface;

/**
 * Hands out the next member number for a church, for example GRC-0042 (D-030).
 * The church row is locked while the counter moves, so two members added at the same moment never
 * get the same number, and a number is never reused after a member is deleted.
 * It does not open a transaction itself: the Action that calls it owns the transaction, and the lock lasts until that ends.
 */
class MemberNumberGenerator
{
    /**
     * Takes the church repository.
     */
    public function __construct(private readonly ChurchRepositoryInterface $churches) {}

    /**
     * Reserves and returns the church's next member number.
     */
    public function next(string $churchId): string
    {
        $church = $this->churches->lock($churchId);

        if ($church->member_number_prefix === null) {
            throw new MemberNumberPrefixMissingException;
        }

        $church = $this->churches->incrementMemberNumberSequence($church);

        return sprintf('%s-%04d', $church->member_number_prefix, $church->member_number_seq);
    }
}
