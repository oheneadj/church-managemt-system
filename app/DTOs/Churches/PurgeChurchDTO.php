<?php

declare(strict_types=1);

namespace App\DTOs\Churches;

use App\Models\Church;

/**
 * A request to purge a cancelled church's personal data once its grace period is over.
 */
final readonly class PurgeChurchDTO
{
    /**
     * Holds the church.
     */
    public function __construct(public Church $church) {}
}
