<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown inside a text job when the church no longer has the credits to pay for it. Retrying cannot help, so the job
 * gives up at once, and the message log records the text as failed. The text was never sent.
 */
class InsufficientSmsCreditsException extends RuntimeException {}
