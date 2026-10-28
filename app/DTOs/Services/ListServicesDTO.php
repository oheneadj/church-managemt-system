<?php

declare(strict_types=1);

namespace App\DTOs\Services;

use App\DTOs\Concerns\ResolvesActor;
use App\Enums\ServiceType;
use App\Http\Requests\Services\ListServicesRequest;
use App\Models\User;

/**
 * What the service list is asked for: the viewer, an optional date range, type and open state, and the page size.
 */
final readonly class ListServicesDTO
{
    use ResolvesActor;

    /**
     * Holds the viewer, filters and page size.
     */
    public function __construct(
        public User $viewer,
        public int $perPage,
        public ?string $from = null,
        public ?string $to = null,
        public ?ServiceType $type = null,
        public ?bool $isOpen = null,
    ) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ListServicesRequest $request): self
    {
        $v = $request->validated();

        return new self(
            viewer: self::actorFrom($request),
            perPage: (int) ($v['per_page'] ?? config('limits.per_page_default')),
            from: $v['from'] ?? null,
            to: $v['to'] ?? null,
            type: isset($v['type']) ? ServiceType::from($v['type']) : null,
            isOpen: isset($v['is_open']) ? (bool) $v['is_open'] : null,
        );
    }
}
