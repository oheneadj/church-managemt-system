<?php

declare(strict_types=1);

namespace App\DTOs\Churches;

use App\Models\Church;

/**
 * A request to cancel a church: it starts the grace period before its personal data is purged.
 */
final readonly class CancelChurchDTO
{
    /**
     * Holds the church.
     */
    public function __construct(public Church $church) {}
}
