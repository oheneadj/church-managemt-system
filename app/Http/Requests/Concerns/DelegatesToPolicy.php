<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\Contribution;
use App\Models\Department;
use App\Models\Family;
use App\Models\FundType;
use App\Models\Member;
use App\Models\Service;
use LogicException;

/**
 * Helpers for Form Requests whose authorize() hands the decision to a Policy, and for reading the models
 * that route binding already resolved (a model of another church never gets this far: it is a 404).
 */
trait DelegatesToPolicy
{
    /**
     * Whether the signed-in user passes the policy check for this ability.
     *
     * @param  array<int, mixed>|mixed  $arguments
     */
    protected function allowed(string $ability, mixed $arguments): bool
    {
        return $this->user()?->can($ability, $arguments) === true;
    }

    /**
     * The member that route binding found for this request.
     */
    public function member(): Member
    {
        $member = $this->route('member');

        return $member instanceof Member ? $member : throw new LogicException('This route has no member.');
    }

    /**
     * The service that route binding found for this request.
     */
    public function service(): Service
    {
        $service = $this->route('service');

        return $service instanceof Service ? $service : throw new LogicException('This route has no service.');
    }

    /**
     * The family that route binding found for this request.
     */
    public function family(): Family
    {
        $family = $this->route('family');

        return $family instanceof Family ? $family : throw new LogicException('This route has no family.');
    }

    /**
     * The department that route binding found for this request.
     */
    public function department(): Department
    {
        $department = $this->route('department');

        return $department instanceof Department ? $department : throw new LogicException('This route has no department.');
    }

    /**
     * The fund type that route binding found for this request.
     */
    public function fundType(): FundType
    {
        $fundType = $this->route('fund_type');

        return $fundType instanceof FundType ? $fundType : throw new LogicException('This route has no fund type.');
    }

    /**
     * The contribution that route binding found for this request.
     */
    public function contribution(): Contribution
    {
        $contribution = $this->route('contribution');

        return $contribution instanceof Contribution ? $contribution : throw new LogicException('This route has no contribution.');
    }
}
