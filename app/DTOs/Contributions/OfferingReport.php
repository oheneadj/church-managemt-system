<?php

declare(strict_types=1);

namespace App\DTOs\Contributions;

use App\Models\Service;

/**
 * What one service received (`totals`, the gifts recorded at it) and the gifts given that day with no service
 * (`noService`). The no-service gifts of a day are reported with that day's first service only (`noServiceCountedHere`),
 * so adding up a day's reports never counts a gift twice. Voided gifts are not in any number.
 */
final readonly class OfferingReport
{
    /**
     * Holds the service and its figures.
     */
    public function __construct(
        public Service $service,
        public OfferingSection $totals,
        public OfferingSection $noService,
        public bool $noServiceCountedHere,
    ) {}
}
