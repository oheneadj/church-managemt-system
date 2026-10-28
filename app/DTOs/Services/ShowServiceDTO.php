<?php

declare(strict_types=1);

namespace App\DTOs\Services;

use App\Models\Service;

/**
 * A request to show one service.
 */
final readonly class ShowServiceDTO
{
    /**
     * Holds the service.
     */
    public function __construct(public Service $service) {}
}
