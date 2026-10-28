<?php

declare(strict_types=1);

namespace App\Http\Requests\Departments;

use App\Models\Department;

/**
 * Validates a new department.
 */
class StoreDepartmentRequest extends DepartmentInputRequest
{
    /**
     * Asks the policy whether the caller may create (department).
     */
    public function authorize(): bool
    {
        return $this->allowed('create', Department::class);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->departmentRules(partial: false);
    }
}
