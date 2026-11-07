<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use App\Http\Requests\Concerns\DelegatesToPlatformPolicy;
use App\Models\SmsPackage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the SMS credit package list's page size.
 */
class ListSmsPackagesRequest extends FormRequest
{
    use DelegatesToPlatformPolicy;

    /**
     * Asks the policy whether the platform admin may list the packages.
     */
    public function authorize(): bool
    {
        return $this->platformAllowed('viewAny', SmsPackage::class);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.config('limits.per_page_max')],
        ];
    }
}
