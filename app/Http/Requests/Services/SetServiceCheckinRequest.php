<?php

declare(strict_types=1);

namespace App\Http\Requests\Services;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates opening or closing a service for check-in.
 */
class SetServiceCheckinRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may change this service.
     */
    public function authorize(): bool
    {
        return $this->allowed('update', $this->service());
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['is_open' => ['required', 'boolean']];
    }
}
