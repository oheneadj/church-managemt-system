<?php

declare(strict_types=1);

use App\Actions\Health\CheckHealthAction;
use App\Services\HealthCheck;

it('is healthy when every check passes', function (): void {
    $report = (new CheckHealthAction(new HealthCheck(['database' => fn () => true, 'cache' => fn () => true])))->execute();

    expect($report->healthy)->toBeTrue()->and($report->checks)->toBe(['database' => 'ok', 'cache' => 'ok']);
});

it('is degraded when any check fails, and names which one', function (): void {
    $report = (new CheckHealthAction(new HealthCheck(['database' => fn () => true, 'cache' => fn () => throw new RuntimeException('down')])))->execute();

    expect($report->healthy)->toBeFalse()->and($report->checks)->toBe(['database' => 'ok', 'cache' => 'error']);
});
