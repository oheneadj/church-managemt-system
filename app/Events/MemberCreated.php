<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A member was added. Fired by the Action inside its transaction.
 */
class MemberCreated
{
    use Dispatchable;

    /**
     * Holds the member and actor.
     */
    public function __construct(public readonly Member $member, public readonly User $actor) {}
}
