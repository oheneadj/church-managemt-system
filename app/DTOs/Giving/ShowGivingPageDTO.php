<?php

declare(strict_types=1);

namespace App\DTOs\Giving;

use App\Models\Church;

/**
 * A request for what the public giving page needs. The church is the one the address names, never anything the visitor sends.
 */
final readonly class ShowGivingPageDTO
{
    /**
     * Holds the church the page belongs to.
     */
    public function __construct(public Church $church) {}

    /**
     * Builds the DTO for the church the request's address belongs to.
     */
    public static function fromRequest(): self
    {
        return new self(app('current.church'));
    }
}
