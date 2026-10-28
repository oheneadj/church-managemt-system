<?php

declare(strict_types=1);

namespace App\Http\Requests\Departments;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates adding a member to a department. The member must belong to this church.
 */
class AddDepartmentMemberRequest extends FormRequest
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
        return [
            'member_id' => [
                'required', 'ulid',
                Rule::exists('members', 'id')->where('church_id', app('current.church')->getKey())->whereNull('deleted_at'),
            ],
            'role' => ['nullable', 'string', 'max:100'],
        ];
    }
}
