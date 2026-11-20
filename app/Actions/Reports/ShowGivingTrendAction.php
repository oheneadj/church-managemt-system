<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\DTOs\Reports\GivingTrend;
use App\DTOs\Reports\GivingTrendDTO;
use App\Services\GivingTrendBuilder;

/**
 * Builds the giving trend report. It reads only, so it needs no transaction.
 */
final class ShowGivingTrendAction
{
    /**
     * Takes the report builder.
     */
    public function __construct(private readonly GivingTrendBuilder $builder) {}

    /**
     * Builds the report.
     */
    public function execute(GivingTrendDTO $dto): GivingTrend
    {
        return $this->builder->build($dto);
    }
}
