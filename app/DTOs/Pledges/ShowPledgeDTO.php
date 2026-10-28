<?php

declare(strict_types=1);

namespace App\DTOs\Pledges;

use App\Http\Requests\Pledges\ShowPledgeRequest;
use App\Models\Pledge;

/**
 * The pledge to show.
 */
final readonly class ShowPledgeDTO
{
    /**
     * Holds the pledge route binding found.
     */
    public function __construct(public Pledge $pledge) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ShowPledgeRequest $request): self
    {
        return new self($request->pledge());
    }
}
