<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use App\Http\Requests\Concerns\DelegatesToPlatformPolicy;
use App\Models\SmsPackage;
use App\Rules\UniqueSmsPackageName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The SMS credit package field rules shared by create and update (D-069). The limits are in config/credits.php, so a typing
 * mistake such as an extra zero is caught before a church is charged for it.
 */
abstract class SmsPackageInputRequest extends FormRequest
{
    use DelegatesToPlatformPolicy;

    /**
     * The package field rules. On an update every field is optional, but one that is sent must still be good.
     *
     * @return array<string, list<mixed>>
     */
    protected function packageRules(bool $partial): array
    {
        $current = $this->route('sms_package');
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'name' => [...$required, 'string', 'max:'.config('credits.package_name_max'), new UniqueSmsPackageName($current instanceof SmsPackage ? $current->getKey() : null)],
            'credits' => [...$required, 'integer', 'min:1', 'max:'.config('credits.package_credits_max')],
            'price' => [...$required, 'numeric', 'decimal:0,2', 'gt:0', 'max:'.config('credits.package_price_max')],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:'.config('credits.package_sort_order_max')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
