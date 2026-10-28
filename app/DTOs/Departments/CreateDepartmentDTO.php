<?php

declare(strict_types=1);

namespace App\DTOs\Departments;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Departments\StoreDepartmentRequest;
use App\Models\User;

/**
 * A new department.
 */
final readonly class CreateDepartmentDTO
{
    use ResolvesActor;

    public function __construct(
        public User $actor,
        public string $name,
        public ?string $description = null,
        public ?string $headUserId = null,
        public bool $isActive = true,
    ) {}

    public static function fromRequest(StoreDepartmentRequest $request): self
    {
        $v = $request->validated();

        return new self(self::actorFrom($request), $v['name'], $v['description'] ?? null, $v['head_user_id'] ?? null, (bool) ($v['is_active'] ?? true));
    }
}
