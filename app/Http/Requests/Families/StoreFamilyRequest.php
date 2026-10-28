<?php

declare(strict_types=1);

namespace App\Http\Requests\Families;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Models\Family;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a new family. An optional head must be an existing member of this church.
 */
class StoreFamilyRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may create (family).
     */
    public function authorize(): bool
    {
        return $this->allowed('create', Family::class);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'head_member_id' => [
                'nullable', 'ulid',
                Rule::exists('members', 'id')->where('church_id', app('current.church')->getKey())->whereNull('deleted_at'),
            ],
        ];
    }
}
