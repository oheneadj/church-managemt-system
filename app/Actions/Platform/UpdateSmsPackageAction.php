<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\DTOs\Platform\UpdateSmsPackageDTO;
use App\Models\SmsPackage;
use App\Repositories\SmsPackageRepositoryInterface;

/**
 * Changes an SMS credit package, or switches it off by setting is_active to false.
 */
final class UpdateSmsPackageAction
{
    /**
     * Takes the package repository.
     */
    public function __construct(private readonly SmsPackageRepositoryInterface $packages) {}

    /**
     * Changes the package.
     */
    public function execute(UpdateSmsPackageDTO $dto): SmsPackage
    {
        return $this->packages->update($dto->package, $dto->attributes);
    }
}
