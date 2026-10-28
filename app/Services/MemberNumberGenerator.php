<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MemberNumberPrefixMissingException;
use App\Models\Church;

/**
 * Hands out the next member number for a church, for example GRC-0042 (D-030).
 * The church row is locked while the counter moves, so two members added at the same moment never
 * get the same number, and a number is never reused after a member is deleted.
 */
class MemberNumberGenerator
{
    /**
     * Must run inside a transaction: the row lock lasts only until the transaction ends.
     */
    public function next(string $churchId): string
    {
        $locked = Church::query()->whereKey($churchId)->lockForUpdate()->firstOrFail();

        if ($locked->member_number_prefix === null) {
            throw new MemberNumberPrefixMissingException;
        }

        $locked->increment('member_number_seq');

        return sprintf('%s-%04d', $locked->member_number_prefix, $locked->member_number_seq);
    }
}
