<?php

declare(strict_types=1);

namespace App\Http\Requests\Members;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an erasure request. The reason is kept in the change log, so it must not contain personal details.
 */
class EraseMemberRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may erase this member.
     */
    public function authorize(): bool
    {
        return $this->allowed('erase', $this->member());
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:1000']];
    }
}
