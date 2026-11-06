<?php

declare(strict_types=1);

namespace App\DTOs\Church;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Church\UpdateChurchProfileRequest;
use App\Models\Church;
use App\Models\User;
use LogicException;

/**
 * A change to the church's own details: the admin, their church and the details sent (null clears an optional one).
 */
final readonly class UpdateChurchProfileDTO
{
    use ResolvesActor;

    /**
     * Holds the admin, their church and the details to set.
     *
     * @param  array<string, string|null>  $details
     */
    public function __construct(public User $actor, public Church $church, public array $details) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(UpdateChurchProfileRequest $request): self
    {
        $actor = self::actorFrom($request);

        return new self($actor, $actor->church ?? throw new LogicException('A staff member always belongs to a church.'), $request->validated());
    }
}
