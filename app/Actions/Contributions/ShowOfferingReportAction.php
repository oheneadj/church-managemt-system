<?php

declare(strict_types=1);

namespace App\Actions\Contributions;

use App\DTOs\Contributions\OfferingReport;
use App\DTOs\Contributions\ShowOfferingReportDTO;
use App\Services\OfferingReportBuilder;

/**
 * Gives the offering report of one service. It only reads, so it opens no transaction.
 */
final class ShowOfferingReportAction
{
    /**
     * Takes the report builder.
     */
    public function __construct(private readonly OfferingReportBuilder $report) {}

    /**
     * Builds the report for the service in the DTO.
     */
    public function execute(ShowOfferingReportDTO $dto): OfferingReport
    {
        return $this->report->build($dto->service);
    }
}
