<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the daily database backup could not be made or stored. It is reported to the error monitor, because a
 * missed backup is not noticed until the day it is needed. The message never holds the database password.
 */
class BackupFailedException extends RuntimeException
{
    /**
     * Takes the step that failed and the program's exit code.
     */
    public function __construct(string $step, int $exitCode)
    {
        parent::__construct("The database backup failed at the {$step} step (exit code {$exitCode}).");
    }
}
