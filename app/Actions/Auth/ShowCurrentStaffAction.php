<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\Auth\CurrentStaff;
use App\DTOs\Auth\ShowCurrentStaffDTO;

/**
 * Returns the signed-in staff member and their church, so the web app can restore its state after a refresh.
 */
final class ShowCurrentStaffAction
{
    /**
     * Returns the signed-in staff member and their church, so the web app can restore its state after a refresh.
     */
    public function execute(ShowCurrentStaffDTO $dto): CurrentStaff
    {
        return new CurrentStaff($dto->user, $dto->church);
    }
}
