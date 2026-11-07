<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\DTOs\Platform\CreateSmsPackageDTO;
use App\Models\SmsPackage;
use App\Repositories\SmsPackageRepositoryInterface;

/**
 * Adds an SMS credit package.
 */
final class CreateSmsPackageAction
{
    /**
     * Takes the package repository.
     */
    public function __construct(private readonly SmsPackageRepositoryInterface $packages) {}

    /**
     * Creates the package.
     */
    public function execute(CreateSmsPackageDTO $dto): SmsPackage
    {
        return $this->packages->create([
            'name' => $dto->name,
            'credits' => $dto->credits,
            'price' => $dto->price,
            'sort_order' => $dto->sortOrder,
            'is_active' => $dto->isActive,
        ]);
    }
}
