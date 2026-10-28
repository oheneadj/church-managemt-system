<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A member's personal data was erased at their request. Fired by the Action inside its transaction.
 */
class MemberErased
{
    use Dispatchable;

    /**
     * Holds the member, the admin who erased them and the reason given.
     */
    public function __construct(public readonly Member $member, public readonly User $actor, public readonly string $reason) {}
}
