<?php

declare(strict_types=1);

namespace App\Http\Requests\SmsCredits;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Models\SmsCreditPurchase;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Asks to see the SMS credit packages on sale. There is nothing to validate.
 */
class ListSmsCreditPackagesRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may buy credits.
     */
    public function authorize(): bool
    {
        return $this->allowed('viewAny', SmsCreditPurchase::class);
    }

    /**
     * No rules: the list takes no input.
     *
     * @return array<string, never>
     */
    public function rules(): array
    {
        return [];
    }
}
