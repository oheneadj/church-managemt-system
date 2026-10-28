<?php

declare(strict_types=1);

namespace App\Http\Requests\Departments;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Checks the caller may change this department's members. It has no fields to validate.
 */
class RemoveDepartmentMemberRequest extends FormRequest
{
    use DelegatesToPolicy;

    public function authorize(): bool
    {
        return $this->allowed('update', $this->department());
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
