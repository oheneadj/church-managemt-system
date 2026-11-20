<?php

declare(strict_types=1);

namespace App\Http\Requests\Pledges;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Checks that the caller may see this pledge. It takes no input.
 */
class ShowPledgeRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may view this pledge.
     */
    public function authorize(): bool
    {
        return $this->allowed('view', $this->pledge());
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
