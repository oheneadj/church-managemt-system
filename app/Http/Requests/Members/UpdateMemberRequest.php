<?php

declare(strict_types=1);

namespace App\Http\Requests\Members;

use App\Models\Member;

/**
 * Validates a member update. Any subset of fields may be sent; only those are changed.
 */
class UpdateMemberRequest extends MemberInputRequest
{
    public function authorize(): bool
    {
        return $this->allowed('update', $this->member());
    }

    protected function subject(): ?Member
    {
        return $this->member();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->memberRules();
    }
}
