<?php

declare(strict_types=1);

namespace App\DTOs\FundTypes;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\FundTypes\StoreFundTypeRequest;
use App\Models\User;

/**
 * A new fund type.
 */
final readonly class CreateFundTypeDTO
{
    use ResolvesActor;

    /**
     * Holds the actor and the fund type's fields.
     */
    public function __construct(
        public User $actor,
        public string $name,
        public ?string $description = null,
        public int $sortOrder = 0,
        public bool $isActive = true,
    ) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(StoreFundTypeRequest $request): self
    {
        $v = $request->validated();

        return new self(self::actorFrom($request), $v['name'], $v['description'] ?? null, (int) ($v['sort_order'] ?? 0), (bool) ($v['is_active'] ?? true));
    }
}
