<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\DTOs\Services\CreateServiceDTO;
use App\Models\Service;
use App\Repositories\ServiceRepositoryInterface;
use App\Services\CheckinCodeGenerator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Creates a service with a check-in code made by the system. If two services get the same code at the same
 * moment, the database's unique index refuses the second one and a new code is tried.
 */
final class CreateServiceAction
{
    /**
     * Takes the service repository and the code generator.
     */
    public function __construct(private readonly ServiceRepositoryInterface $services, private readonly CheckinCodeGenerator $codes) {}

    /**
     * Creates the service, closed, with its code.
     */
    public function execute(CreateServiceDTO $dto): Service
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn (): Service => $this->services->withAttendanceCount($this->services->create([
                    'church_id' => $dto->actor->church_id,
                    'name' => $dto->name,
                    'type' => $dto->type,
                    'checkin_code' => $this->codes->next(),
                    'service_date' => $dto->serviceDate,
                    'starts_at' => $dto->startsAt,
                    'ends_at' => $dto->endsAt,
                    'notes' => $dto->notes,
                    'created_by' => $dto->actor->id,
                ])));
            } catch (UniqueConstraintViolationException $clash) {
                if ($attempt >= config('attendance.checkin_code_attempts')) {
                    throw $clash;
                }
            }
        }
    }
}
