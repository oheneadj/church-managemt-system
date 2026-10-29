<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Service;
use App\Repositories\ServiceRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Saves a new service with a check-in code made by the system. If two services get the same code at the same moment,
 * the database's unique index refuses the second one and a new code is tried. Each try runs in its own nested
 * transaction (a savepoint), because Postgres cancels the whole outer transaction after a failed statement. The
 * business transaction itself is still the caller's. It is shared by creating a service by hand and by a schedule.
 */
class ServiceCreator
{
    /**
     * Takes the service repository and the code generator.
     */
    public function __construct(private readonly ServiceRepositoryInterface $services, private readonly CheckinCodeGenerator $codes) {}

    /**
     * Saves the service, closed, with its code, and loads its attendance count.
     *
     * @param  array<string, mixed>  $attributes  everything except the check-in code
     */
    public function create(array $attributes): Service
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn (): Service => $this->services->withAttendanceCount($this->services->create([...$attributes, 'checkin_code' => $this->codes->next()])));
            } catch (UniqueConstraintViolationException $clash) {
                if ($attempt >= config('attendance.checkin_code_attempts')) {
                    throw $clash;
                }
            }
        }
    }
}
