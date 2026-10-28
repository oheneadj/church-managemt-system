<?php

declare(strict_types=1);

namespace App\Http\Requests\Members;

use App\Enums\MilestoneType;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a new milestone (baptism, confirmation, ...). It cannot be dated in the future.
 */
class StoreMilestoneRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may change this member.
     */
    public function authorize(): bool
    {
        return $this->allowed('update', $this->member());
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(MilestoneType::class)],
            'occurred_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'officiant' => ['nullable', 'string', 'max:255'],
            'cohort' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
