<?php

declare(strict_types=1);

namespace App\Http\Requests\Members;

use App\Enums\MemberStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a status change. The note is the reason kept in the change log.
 */
class UpdateMemberStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(MemberStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function status(): MemberStatus
    {
        return MemberStatus::from((string) $this->validated('status'));
    }
}
