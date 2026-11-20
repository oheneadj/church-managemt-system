<?php

declare(strict_types=1);

namespace App\DTOs\Exports;

use App\DTOs\Concerns\ResolvesActor;
use App\Enums\ExportType;
use App\Http\Requests\Exports\ExportContributionsRequest;
use App\Http\Requests\Exports\ExportMembersRequest;
use App\Models\User;

/**
 * A request for an export: who asked, what kind, and the filters they chose (those of the member list, or of the gift list).
 */
final readonly class QueueExportDTO
{
    use ResolvesActor;

    /**
     * Holds the staff member, the kind of export and the filters, as sent.
     *
     * @param  array<string, string>  $filters
     */
    public function __construct(public User $actor, public ExportType $type, public array $filters) {}

    /**
     * Builds the DTO for a member list export from the validated request.
     */
    public static function forMembers(ExportMembersRequest $request): self
    {
        return new self(self::actorFrom($request), ExportType::Members, $request->validated());
    }

    /**
     * Builds the DTO for a giving records export from the validated request.
     */
    public static function forContributions(ExportContributionsRequest $request): self
    {
        return new self(self::actorFrom($request), ExportType::Contributions, $request->validated());
    }
}
