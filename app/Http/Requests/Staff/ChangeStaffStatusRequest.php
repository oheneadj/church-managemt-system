<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates switching a staff member on or off.
 */
class ChangeStaffStatusRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may change this staff member.
     */
    public function authorize(): bool
    {
        return $this->allowed('update', $this->staffMember());
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }
}
