<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A member moved to another status, with the reason given.
 */
class MemberStatusChanged
{
    use Dispatchable;

    /**
     * Holds the member, actor, previous and note.
     */
    public function __construct(
        public readonly Member $member,
        public readonly User $actor,
        public readonly MemberStatus $previous,
        public readonly ?string $note,
    ) {}
}
