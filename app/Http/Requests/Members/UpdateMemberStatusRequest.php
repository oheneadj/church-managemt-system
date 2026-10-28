<?php

declare(strict_types=1);

namespace App\Http\Requests\Members;

use App\Enums\MemberStatus;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a status change. The note is the reason kept in the change log.
 */
class UpdateMemberStatusRequest extends FormRequest
{
    use DelegatesToPolicy;

    public function authorize(): bool
    {
        return $this->allowed('changeStatus', [$this->member(), $this->status()]);
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

    /**
     * The requested status. Before validation has run an unknown value is still possible, so this
     * falls back to a status that needs no extra permission; validation then rejects the request.
     */
    public function status(): MemberStatus
    {
        return MemberStatus::tryFrom((string) $this->input('status')) ?? MemberStatus::Active;
    }
}
