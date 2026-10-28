<?php

declare(strict_types=1);

namespace App\Http\Requests\Contributions;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a void. A reason is required, so every void can be explained later (docs/04, Corrections and Voids).
 */
class VoidContributionRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may void this gift.
     */
    public function authorize(): bool
    {
        return $this->allowed('void', $this->contribution());
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:2000']];
    }
}
