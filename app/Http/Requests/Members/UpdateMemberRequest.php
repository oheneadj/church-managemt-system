<?php

declare(strict_types=1);

namespace App\Http\Requests\Members;

use App\Models\Member;

/**
 * Validates a member update. Any subset of fields may be sent; only those are changed.
 */
class UpdateMemberRequest extends MemberInputRequest
{
    /**
     * Asks the policy whether the caller may change this member.
     */
    public function authorize(): bool
    {
        return $this->allowed('update', $this->member());
    }

    /**
     * The member being changed, or null when a member is being added.
     */
    protected function subject(): ?Member
    {
        return $this->member();
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->memberRules();
    }
}
