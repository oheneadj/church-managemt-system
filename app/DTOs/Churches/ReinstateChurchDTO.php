<?php

declare(strict_types=1);

namespace App\DTOs\Churches;

use App\Models\Church;

/**
 * A request to take back a church's cancellation, possible only before its data is purged.
 */
final readonly class ReinstateChurchDTO
{
    /**
     * Holds the church.
     */
    public function __construct(public Church $church) {}
}
