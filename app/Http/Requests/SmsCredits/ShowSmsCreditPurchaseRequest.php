<?php

declare(strict_types=1);

namespace App\Http\Requests\SmsCredits;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Models\SmsCreditPurchase;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * Asks to read one purchase of SMS credits. A purchase of another church never gets here: route binding makes it a 404.
 */
class ShowSmsCreditPurchaseRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may read this purchase.
     */
    public function authorize(): bool
    {
        return $this->allowed('view', $this->purchase());
    }

    /**
     * No rules: reading takes no input.
     *
     * @return array<string, never>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * The purchase that route binding found for this request.
     */
    public function purchase(): SmsCreditPurchase
    {
        $purchase = $this->route('sms_credit_purchase');

        return $purchase instanceof SmsCreditPurchase ? $purchase : throw new LogicException('This route has no purchase.');
    }
}
