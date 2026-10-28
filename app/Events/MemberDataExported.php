<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Everything held about a member was exported (a subject access request). Fired by the Action.
 */
class MemberDataExported
{
    use Dispatchable;

    /**
     * Holds the member and the staff member who exported the data.
     */
    public function __construct(public readonly Member $member, public readonly User $actor) {}
}
