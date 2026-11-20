<?php

declare(strict_types=1);

namespace App\Actions\Health;

use App\DTOs\Health\HealthReport;
use App\Services\HealthCheck;

/**
 * Runs the readiness checks and says whether the app is fit to serve traffic.
 */
final class CheckHealthAction
{
    /**
     * Takes the health check.
     */
    public function __construct(private readonly HealthCheck $health) {}

    /**
     * Runs the readiness checks and says whether the app is fit to serve traffic.
     */
    public function execute(): HealthReport
    {
        $checks = $this->health->run();

        return new HealthReport($checks, ! in_array('error', $checks, true), now());
    }
}
