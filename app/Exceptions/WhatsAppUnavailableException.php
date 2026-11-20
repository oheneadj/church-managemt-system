<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the chosen WhatsApp driver cannot be used: its credentials are missing or it is not allowed here.
 */
class WhatsAppUnavailableException extends RuntimeException {}
