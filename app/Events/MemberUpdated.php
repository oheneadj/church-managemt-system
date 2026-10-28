<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A member's details changed. Carries the old and new values of the fields that changed.
 */
class MemberUpdated
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function __construct(
        public readonly Member $member,
        public readonly User $actor,
        public readonly array $old,
        public readonly array $new,
    ) {}
}
