<?php

declare(strict_types=1);

namespace App\Http\Requests\Departments;

/**
 * Validates a new department.
 */
class StoreDepartmentRequest extends DepartmentInputRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->departmentRules(partial: false);
    }
}
