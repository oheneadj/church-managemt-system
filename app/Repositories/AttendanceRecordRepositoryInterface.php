<?php

declare(strict_types=1);

namespace App\Repositories;

use DateTimeInterface;

/**
 * The attendance counts the member profile shows.
 */
interface AttendanceRecordRepositoryInterface
{
    /**
     * How many services the member was recorded at since the given moment.
     */
    public function countForMemberSince(string $memberId, DateTimeInterface $since): int;
}
