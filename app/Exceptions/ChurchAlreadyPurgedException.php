<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when someone tries to reinstate a church whose personal data has already been purged. That cannot be undone.
 */
class ChurchAlreadyPurgedException extends RuntimeException
{
    /**
     * Says why in plain words, for the person running the command.
     */
    public function __construct()
    {
        parent::__construct('This church has already been purged. Its personal data is gone and cannot be restored.');
    }
}
