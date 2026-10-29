<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\DTOs\Services\SetServiceCheckinDTO;
use App\Exceptions\CheckinOnlyOnServiceDayException;
use App\Models\Service;
use App\Repositories\ServiceRepositoryInterface;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * Opens or closes a service for check-in. Doing it twice is harmless. Check-in can only be opened on the service's own
 * day, in the church's calendar (D-052); closing is always allowed.
 */
final class SetServiceCheckinAction
{
    /**
     * Takes the service repository.
     */
    public function __construct(private readonly ServiceRepositoryInterface $services) {}

    /**
     * Sets the open flag. The time it was opened is kept only when check-in actually opens, so asking to open an
     * open service again does not move it to the back of the queue for WhatsApp CHECKIN.
     *
     * @throws CheckinOnlyOnServiceDayException
     */
    public function execute(SetServiceCheckinDTO $dto): Service
    {
        $opening = $dto->isOpen && ! $dto->service->getOriginal('is_open');

        if ($opening && ! $this->isToday($dto->service)) {
            throw new CheckinOnlyOnServiceDayException;
        }

        $service = $dto->service->forceFill(['is_open' => $dto->isOpen]);

        if ($opening) {
            $service->checkin_opened_at = now();
        }

        return $this->services->withAttendanceCount($this->services->save($service));
    }

    /**
     * Whether the service is dated today in the church's own calendar.
     */
    private function isToday(Service $service): bool
    {
        $church = $service->church ?? throw new LogicException('A service always belongs to a church.');

        return $service->service_date->toDateString() === CarbonImmutable::now($church->timezone)->toDateString();
    }
}
