<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\Department;
use App\Models\Family;
use App\Models\Member;
use LogicException;

/**
 * Helpers for Form Requests whose authorize() hands the decision to a Policy, and for reading the models
 * that route binding already resolved (a model of another church never gets this far: it is a 404).
 */
trait DelegatesToPolicy
{
    /**
     * @param  array<int, mixed>|mixed  $arguments
     */
    protected function allowed(string $ability, mixed $arguments): bool
    {
        return $this->user()?->can($ability, $arguments) === true;
    }

    public function member(): Member
    {
        $member = $this->route('member');

        return $member instanceof Member ? $member : throw new LogicException('This route has no member.');
    }

    public function family(): Family
    {
        $family = $this->route('family');

        return $family instanceof Family ? $family : throw new LogicException('This route has no family.');
    }

    public function department(): Department
    {
        $department = $this->route('department');

        return $department instanceof Department ? $department : throw new LogicException('This route has no department.');
    }
}
