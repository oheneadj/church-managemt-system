<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\PledgeStatus;
use App\Repositories\PledgeRepositoryInterface;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Checks that the pledge a gift names can take it (D-047): it is one of this church's, it is for the same member and
 * the same fund as the gift, and it is not already paid in full. Nothing is linked by matching; the gift names its pledge.
 */
class ValidPledgeForGift implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    /**
     * Receives the rest of the request, so the rule can compare the gift's member and fund.
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Fails the field with the first reason the pledge cannot take this gift.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pledge = is_string($value) ? app(PledgeRepositoryInterface::class)->find($value) : null;

        $reason = match (true) {
            $pledge === null => 'The selected pledge is invalid.',
            $pledge->status === PledgeStatus::Fulfilled => 'This pledge is already paid in full.',
            ($this->data['member_id'] ?? null) !== $pledge->member_id => 'This pledge belongs to another member.',
            ($this->data['fund_type_id'] ?? null) !== $pledge->fund_type_id => 'This pledge is for a different fund.',
            default => null,
        };

        if ($reason !== null) {
            $fail($reason);
        }
    }
}
