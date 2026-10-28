<?php

declare(strict_types=1);

namespace App\DTOs\Health;

use DateTimeInterface;

/**
 * The outcome of the readiness checks: ok or error per dependency, and the overall verdict.
 */
final readonly class HealthReport
{
    /**
     * Holds the checks, healthy and checked at.
     *
     * @param  array<string, 'ok'|'error'>  $checks
     */
    public function __construct(public array $checks, public bool $healthy, public DateTimeInterface $checkedAt) {}
}
