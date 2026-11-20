<?php

declare(strict_types=1);

namespace App\DTOs\Attendance;

/**
 * What a bulk check-in did: how many members were newly recorded, how many were already checked in, and which ones
 * could not be checked in at all and why.
 */
final readonly class AttendanceRecorded
{
    /**
     * Holds the counts and the per-member problems.
     *
     * @param  list<array{member_id: string, message: string}>  $errors
     */
    public function __construct(public int $recorded, public int $alreadyCheckedIn, public array $errors = []) {}
}
