<?php

declare(strict_types=1);

namespace App\DTOs\Families;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Families\StoreFamilyRequest;
use App\Models\User;

/**
 * A new family, optionally with the member who becomes its first head.
 */
final readonly class CreateFamilyDTO
{
    use ResolvesActor;

    public function __construct(
        public User $actor,
        public string $name,
        public ?string $notes = null,
        public ?string $headMemberId = null,
    ) {}

    public static function fromRequest(StoreFamilyRequest $request): self
    {
        $v = $request->validated();

        return new self(self::actorFrom($request), $v['name'], $v['notes'] ?? null, $v['head_member_id'] ?? null);
    }
}
