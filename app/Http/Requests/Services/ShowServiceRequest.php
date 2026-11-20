<?php

declare(strict_types=1);

namespace App\Http\Requests\Services;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Checks that the caller may open this service. Nothing is read from the body.
 */
class ShowServiceRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may open this service.
     */
    public function authorize(): bool
    {
        return $this->allowed('view', $this->service());
    }

    /**
     * There is no input to check.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
