<?php

declare(strict_types=1);

namespace App\DTOs\Pledges;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Pledges\UpdatePledgeRequest;
use App\Models\Pledge;
use App\Models\User;

/**
 * A partial pledge update: only the fields that were sent (description, amount_pledged, target_date).
 */
final readonly class UpdatePledgeDTO
{
    use ResolvesActor;

    /**
     * Holds the actor, the pledge and the attributes to change.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public User $actor, public Pledge $pledge, public array $attributes) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(UpdatePledgeRequest $request): self
    {
        return new self(self::actorFrom($request), $request->pledge(), $request->validated());
    }
}
