<?php

declare(strict_types=1);

namespace App\Http\Requests\Departments;

use App\Models\Department;

/**
 * Validates a new department.
 */
class StoreDepartmentRequest extends DepartmentInputRequest
{
    public function authorize(): bool
    {
        return $this->allowed('create', Department::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->departmentRules(partial: false);
    }
}
