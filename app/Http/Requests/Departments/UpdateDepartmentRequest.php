<?php

declare(strict_types=1);

namespace App\Http\Requests\Departments;

/**
 * Validates a department update. Any subset of fields may be sent. Setting is_active to false retires it.
 */
class UpdateDepartmentRequest extends DepartmentInputRequest
{
    /**
     * Asks the policy whether the caller may change this department.
     */
    public function authorize(): bool
    {
        return $this->allowed('update', $this->department());
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->departmentRules(partial: true);
    }
}
