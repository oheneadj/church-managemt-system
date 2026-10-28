<?php

declare(strict_types=1);

namespace App\Http\Requests\Members;

use App\Enums\MemberStatus;
use App\Models\Member;
use Illuminate\Validation\Rule;

/**
 * Validates a new member. The route's policy check has already run, so authorize() lets it through.
 */
class StoreMemberRequest extends MemberInputRequest
{
    public function authorize(): bool
    {
        return $this->allowed('create', Member::class);
    }

    protected function subject(): ?Member
    {
        return null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->memberRules(),
            // A new person is either a prospect or already received; the other statuses are for later.
            'status' => ['sometimes', Rule::in([MemberStatus::Prospect->value, MemberStatus::Active->value])],
        ];
    }
}
