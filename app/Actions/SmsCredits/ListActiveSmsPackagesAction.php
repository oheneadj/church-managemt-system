<?php

declare(strict_types=1);

namespace App\Actions\SmsCredits;

use App\Models\SmsPackage;
use App\Repositories\SmsPackageRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Lists the SMS credit packages a church may buy: the ones the platform owner has switched on.
 */
final class ListActiveSmsPackagesAction
{
    /**
     * Takes the package repository.
     */
    public function __construct(private readonly SmsPackageRepositoryInterface $packages) {}

    /**
     * The packages on sale, in the order the owner set.
     *
     * @return Collection<int, SmsPackage>
     */
    public function execute(): Collection
    {
        return $this->packages->active();
    }
}
