<?php

declare(strict_types=1);

namespace App\DTOs\Contributions;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Contributions\VoidContributionRequest;
use App\Models\Contribution;
use App\Models\User;

/**
 * A request to void a gift, with the reason the person gave.
 */
final readonly class VoidContributionDTO
{
    use ResolvesActor;

    /**
     * Holds the actor, the gift and the reason.
     */
    public function __construct(public User $actor, public Contribution $contribution, public string $reason) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(VoidContributionRequest $request): self
    {
        return new self(self::actorFrom($request), $request->contribution(), $request->validated('reason'));
    }
}
