<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use App\Enums\StaffPermission;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Repositories\UserRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates granting and removing single permissions on top of a staff member's role. At least one list is needed, a
 * permission cannot be in both, and a permission the role itself gives cannot be removed here: change the role instead.
 */
class ChangeStaffPermissionsRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may change this staff member.
     */
    public function authorize(): bool
    {
        return $this->allowed('update', $this->staffMember());
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $grant = $this->input('grant');

        return [
            'grant' => ['required_without:revoke', 'array'],
            'grant.*' => [Rule::enum(StaffPermission::class), 'distinct'],
            'revoke' => ['required_without:grant', 'array'],
            'revoke.*' => [Rule::enum(StaffPermission::class), 'distinct', Rule::notIn(is_array($grant) ? $grant : [])],
        ];
    }

    /**
     * Refuses removing a permission that comes from the person's role.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $fromRole = app(UserRepositoryInterface::class)->rolePermissions($this->staffMember());

            foreach ((array) $this->input('revoke', []) as $index => $permission) {
                if (is_string($permission) && in_array($permission, $fromRole, true)) {
                    $validator->errors()->add("revoke.{$index}", 'This permission comes from the person\'s role. Change their role to take it away.');
                }
            }
        }];
    }
}
