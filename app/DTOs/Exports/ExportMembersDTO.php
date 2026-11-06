<?php

declare(strict_types=1);

namespace App\DTOs\Exports;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Exports\ExportMembersRequest;
use App\Models\User;

/**
 * A request for the member list as a CSV: who asked and the member list filters they chose.
 */
final readonly class ExportMembersDTO
{
    use ResolvesActor;

    /**
     * Holds the staff member and the filters, as sent.
     *
     * @param  array<string, string>  $filters
     */
    public function __construct(public User $actor, public array $filters) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ExportMembersRequest $request): self
    {
        return new self(self::actorFrom($request), $request->validated());
    }
}
