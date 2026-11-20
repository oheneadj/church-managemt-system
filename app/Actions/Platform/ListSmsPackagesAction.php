<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\DTOs\Platform\ListSmsPackagesDTO;
use App\Models\SmsPackage;
use App\Repositories\SmsPackageRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lists one page of the SMS credit packages, switched off ones too, for the platform admin.
 */
final class ListSmsPackagesAction
{
    /**
     * Takes the package repository.
     */
    public function __construct(private readonly SmsPackageRepositoryInterface $packages) {}

    /**
     * Lists one page of packages.
     *
     * @return LengthAwarePaginator<int, SmsPackage>
     */
    public function execute(ListSmsPackagesDTO $dto): LengthAwarePaginator
    {
        return $this->packages->paginate($dto->perPage);
    }
}
