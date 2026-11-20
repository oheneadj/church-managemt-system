<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Like DelegatesToPolicy, for the platform admin side (D-069): the signed-in person is the platform admin, read from the
 * `platform` guard, never a church's staff member from the default guard.
 */
trait DelegatesToPlatformPolicy
{
    /**
     * Whether the signed-in platform admin passes the policy check for this ability.
     *
     * @param  array<int, mixed>|mixed  $arguments
     */
    protected function platformAllowed(string $ability, mixed $arguments): bool
    {
        return $this->user('platform')?->can($ability, $arguments) === true;
    }
}
