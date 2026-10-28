<?php

declare(strict_types=1);

namespace App\Http\Requests\Departments;

/**
 * Validates a department update. Any subset of fields may be sent. Setting is_active to false retires it.
 */
class UpdateDepartmentRequest extends DepartmentInputRequest
{
    public function authorize(): bool
    {
        return $this->allowed('update', $this->department());
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->departmentRules(partial: true);
    }
}
