<?php

declare(strict_types=1);

namespace App\Http\Requests\Contributions;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Checks that the caller may see this gift. It takes no input.
 */
class ShowContributionRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may view this gift.
     */
    public function authorize(): bool
    {
        return $this->allowed('view', $this->contribution());
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
