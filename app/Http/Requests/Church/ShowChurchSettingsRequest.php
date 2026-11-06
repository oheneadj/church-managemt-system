<?php

declare(strict_types=1);

namespace App\Http\Requests\Church;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reads the signed-in staff member's own church settings. Anyone signed in to the church may.
 */
class ShowChurchSettingsRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may read their church's settings.
     */
    public function authorize(): bool
    {
        $church = $this->user()?->church;

        return $church !== null && $this->allowed('viewSettings', $church);
    }

    /**
     * There is nothing to validate: the request has no input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
