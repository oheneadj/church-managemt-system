<?php

declare(strict_types=1);

namespace App\DTOs\Contributions;

use App\Http\Requests\Contributions\ShowContributionRequest;
use App\Models\Contribution;

/**
 * The gift to show.
 */
final readonly class ShowContributionDTO
{
    /**
     * Holds the gift route binding found.
     */
    public function __construct(public Contribution $contribution) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ShowContributionRequest $request): self
    {
        return new self($request->contribution());
    }
}
