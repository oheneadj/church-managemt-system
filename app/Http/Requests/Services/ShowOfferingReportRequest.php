<?php

declare(strict_types=1);

namespace App\Http\Requests\Services;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Checks that the caller may see what this service received. It takes no input.
 */
class ShowOfferingReportRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may view this service's offering report.
     */
    public function authorize(): bool
    {
        return $this->allowed('viewOfferingReport', $this->service());
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
