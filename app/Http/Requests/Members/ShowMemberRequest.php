<?php

declare(strict_types=1);

namespace App\Http\Requests\Members;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Checks the viewer may see this member. It has no fields to validate.
 */
class ShowMemberRequest extends FormRequest
{
    use DelegatesToPolicy;

    public function authorize(): bool
    {
        return $this->allowed('view', $this->member());
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
