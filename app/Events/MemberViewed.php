<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A staff member opened a member's full record, which includes encrypted personal data (docs/03, section 36 of the agreement).
 */
class MemberViewed
{
    use Dispatchable;

    /**
     * Holds the member and viewer.
     */
    public function __construct(public readonly Member $member, public readonly User $viewer) {}
}
