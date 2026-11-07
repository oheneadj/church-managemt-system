<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use App\Models\SmsPackage;

/**
 * Validates a new SMS credit package: a name, a number of credits and a price in GHS.
 */
class StoreSmsPackageRequest extends SmsPackageInputRequest
{
    /**
     * Asks the policy whether the platform admin may add a package.
     */
    public function authorize(): bool
    {
        return $this->platformAllowed('create', SmsPackage::class);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->packageRules(partial: false);
    }
}
