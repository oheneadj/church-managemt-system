<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use App\Models\SmsPackage;
use LogicException;

/**
 * Validates a change to an SMS credit package. Any subset of fields may be sent. Setting is_active to false switches the
 * package off, which is how it stops being sold.
 */
class UpdateSmsPackageRequest extends SmsPackageInputRequest
{
    /**
     * Asks the policy whether the platform admin may change this package.
     */
    public function authorize(): bool
    {
        return $this->platformAllowed('update', $this->smsPackage());
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->packageRules(partial: true);
    }

    /**
     * The package that route binding found for this request.
     */
    public function smsPackage(): SmsPackage
    {
        $package = $this->route('sms_package');

        return $package instanceof SmsPackage ? $package : throw new LogicException('This route has no SMS package.');
    }
}
