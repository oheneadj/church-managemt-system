<?php

declare(strict_types=1);

namespace App\DTOs\Departments;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Departments\ListDepartmentsRequest;
use App\Models\User;

/**
 * What the department list is asked for: the viewer, an optional active filter and the page size.
 */
final readonly class ListDepartmentsDTO
{
    use ResolvesActor;

    public function __construct(public User $viewer, public ?bool $isActive = null, public int $perPage = 25) {}

    public static function fromRequest(ListDepartmentsRequest $request): self
    {
        $v = $request->validated();

        return new self(self::actorFrom($request), isset($v['is_active']) ? (bool) $v['is_active'] : null, (int) ($v['per_page'] ?? 25));
    }
}
