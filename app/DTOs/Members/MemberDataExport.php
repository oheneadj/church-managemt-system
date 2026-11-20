<?php

declare(strict_types=1);

namespace App\DTOs\Members;

use App\Models\Member;
use Carbon\CarbonImmutable;

/**
 * Everything held about a member, ready to be shown to them: the member with all its related records, and when
 * the export was made.
 */
final readonly class MemberDataExport
{
    /**
     * Holds the member (with its relations loaded) and the time of export.
     */
    public function __construct(public Member $member, public CarbonImmutable $exportedAt) {}
}
