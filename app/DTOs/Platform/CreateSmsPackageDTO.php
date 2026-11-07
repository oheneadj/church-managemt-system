<?php

declare(strict_types=1);

namespace App\DTOs\Platform;

use App\Http\Requests\Platform\StoreSmsPackageRequest;
use App\ValueObjects\Money;

/**
 * A new SMS credit package. The price is exact text, normalised to two places by Money (bcmath), never through a float.
 */
final readonly class CreateSmsPackageDTO
{
    /**
     * Holds the package's fields.
     */
    public function __construct(
        public string $name,
        public int $credits,
        public string $price,
        public int $sortOrder,
        public bool $isActive,
    ) {}

    /**
     * Builds the DTO from the validated request. A package starts switched on and at the start of the list.
     */
    public static function fromRequest(StoreSmsPackageRequest $request): self
    {
        $v = $request->validated();

        return new self((string) $v['name'], (int) $v['credits'], Money::ghs((string) $v['price'])->amount, (int) ($v['sort_order'] ?? 0), (bool) ($v['is_active'] ?? true));
    }
}
