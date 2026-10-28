<?php

declare(strict_types=1);

namespace App\Http\Requests\Departments;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The department fields shared by create and update. Names are unique among this church's departments.
 */
abstract class DepartmentInputRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * The department field rules shared by create and update.
     *
     * @return array<string, list<mixed>>
     */
    protected function departmentRules(bool $partial): array
    {
        $churchId = app('current.church')->getKey();
        $department = $this->route('department');

        return [
            'name' => [
                ...($partial ? ['sometimes', 'required'] : ['required']), 'string', 'max:255',
                Rule::unique('departments', 'name')->where('church_id', $churchId)->withoutTrashed()->ignore($department instanceof Department ? $department->getKey() : null),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'head_user_id' => [
                'nullable', 'ulid',
                Rule::exists('users', 'id')->where('church_id', $churchId)->where('is_active', true)->whereNull('deleted_at'),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
