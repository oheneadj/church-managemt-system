<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\DTOs\Reports\AttendanceTrend;
use App\DTOs\Reports\AttendanceTrendDTO;
use App\Services\AttendanceTrendBuilder;

/**
 * Builds the attendance trend report. It reads only, so it needs no transaction.
 */
final class ShowAttendanceTrendAction
{
    /**
     * Takes the report builder.
     */
    public function __construct(private readonly AttendanceTrendBuilder $builder) {}

    /**
     * Builds the report.
     */
    public function execute(AttendanceTrendDTO $dto): AttendanceTrend
    {
        return $this->builder->build($dto);
    }
}
