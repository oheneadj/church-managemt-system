<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\DTOs\Services\CancelServiceDTO;
use App\Exceptions\ServiceHasRecordsException;
use App\Exceptions\ServiceIsOpenException;
use App\Repositories\ServiceRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a service that is closed and that nothing has been recorded at (for example a Sunday the church does not
 * meet). The row is kept, soft-deleted, so a schedule never makes that date again. The row is locked while it is checked, so a
 * check-in that arrives at the same moment cannot slip in after the check.
 */
final class CancelServiceAction
{
    /**
     * Takes the service repository.
     */
    public function __construct(private readonly ServiceRepositoryInterface $services) {}

    /**
     * Cancels the service.
     *
     * @throws ServiceHasRecordsException
     * @throws ServiceIsOpenException
     */
    public function execute(CancelServiceDTO $dto): void
    {
        DB::transaction(function () use ($dto): void {
            $service = $this->services->lockForUpdate($dto->service);

            if ($service->is_open) {
                throw new ServiceIsOpenException;
            }

            if ($this->services->hasRecords($service)) {
                throw new ServiceHasRecordsException;
            }

            $this->services->delete($service);
        });
    }
}
