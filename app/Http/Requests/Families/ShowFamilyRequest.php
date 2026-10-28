<?php

declare(strict_types=1);

namespace App\Http\Requests\Families;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Checks the viewer may see this family. It has no fields to validate.
 */
class ShowFamilyRequest extends FormRequest
{
    use DelegatesToPolicy;

    public function authorize(): bool
    {
        return $this->allowed('view', $this->family());
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
