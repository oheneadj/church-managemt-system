<?php

declare(strict_types=1);

namespace App\Http\Requests\Pledges;

/**
 * Validates a pledge update. Any of the description, amount and target date may be sent. The member and fund cannot
 * change, because gifts are linked to them (D-047).
 */
class UpdatePledgeRequest extends PledgeInputRequest
{
    /**
     * Asks the policy whether the caller may change this pledge.
     */
    public function authorize(): bool
    {
        return $this->allowed('update', $this->pledge());
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->pledgeRules(partial: true);
    }
}
