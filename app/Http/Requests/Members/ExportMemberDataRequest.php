<?php

declare(strict_types=1);

namespace App\Http\Requests\Members;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Checks that the caller may export everything held about this member. Nothing is read from the body.
 */
class ExportMemberDataRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may export this member's data.
     */
    public function authorize(): bool
    {
        return $this->allowed('export', $this->member());
    }

    /**
     * There is no input to check.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
