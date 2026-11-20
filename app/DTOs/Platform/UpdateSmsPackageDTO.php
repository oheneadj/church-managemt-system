<?php

declare(strict_types=1);

namespace App\DTOs\Platform;

use App\Http\Requests\Platform\UpdateSmsPackageRequest;
use App\Models\SmsPackage;
use App\ValueObjects\Money;

/**
 * A partial SMS credit package update: only the fields that were sent. A price is normalised to two places by Money
 * (bcmath), never through a float.
 */
final readonly class UpdateSmsPackageDTO
{
    /**
     * Holds the package and the attributes to change.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public SmsPackage $package, public array $attributes) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(UpdateSmsPackageRequest $request): self
    {
        $attributes = $request->validated();

        if (isset($attributes['price'])) {
            $attributes['price'] = Money::ghs((string) $attributes['price'])->amount;
        }

        return new self($request->smsPackage(), $attributes);
    }
}
