<?php

declare(strict_types=1);

namespace App\Http\Requests\Families;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a family update. The head is changed through the members' roles, not here, so two people
 * can never both hold the head role.
 */
class UpdateFamilyRequest extends FormRequest
{
    use DelegatesToPolicy;

    public function authorize(): bool
    {
        return $this->allowed('update', $this->family());
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
