<?php

declare(strict_types=1);

namespace App\DTOs\Reports;

use App\Enums\MemberStatus;
use Carbon\CarbonImmutable;

/**
 * One step in a member's status history: added with a status (`from` is null), or moved from one status to another.
 */
final readonly class MemberStatusChange
{
    /**
     * Holds the status before (null when the member was just added), the status after and when it happened.
     */
    public function __construct(public ?MemberStatus $from, public MemberStatus $to, public CarbonImmutable $at) {}
}
