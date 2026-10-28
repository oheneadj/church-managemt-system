<?php

declare(strict_types=1);

namespace App\DTOs\Attendance;

use App\Models\Member;
use App\Models\Service;

/**
 * The result of a QR check-in: who and where, and whether they were already in.
 */
final readonly class QrCheckedIn
{
    /**
     * Holds the service, the member and whether this was a repeat.
     */
    public function __construct(public Service $service, public Member $member, public bool $alreadyCheckedIn) {}
}
