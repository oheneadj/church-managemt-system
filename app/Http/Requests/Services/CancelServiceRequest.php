<?php

declare(strict_types=1);

namespace App\Http\Requests\Services;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Checks that the caller may cancel this service. It takes no input.
 */
class CancelServiceRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may cancel this service.
     */
    public function authorize(): bool
    {
        return $this->allowed('cancel', $this->service());
    }

    /**
     * No input to check.
     *
     * @return array<string, never>
     */
    public function rules(): array
    {
        return [];
    }
}
