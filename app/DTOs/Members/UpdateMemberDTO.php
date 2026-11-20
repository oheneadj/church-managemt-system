<?php

declare(strict_types=1);

namespace App\DTOs\Members;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Members\UpdateMemberRequest;
use App\Models\Member;
use App\Models\User;

/**
 * A partial update: only the fields the client sent. A plain array is used for them because
 * "not sent" and "set to null" mean different things and typed properties cannot tell them apart.
 */
final readonly class UpdateMemberDTO
{
    use ResolvesActor;

    /**
     * Holds the actor, member, attributes and department ids.
     *
     * @param  array<string, mixed>  $attributes  validated member columns that were sent
     * @param  list<string>|null  $departmentIds  null when the client did not send departments
     */
    public function __construct(
        public User $actor,
        public Member $member,
        public array $attributes,
        public ?array $departmentIds = null,
    ) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(UpdateMemberRequest $request): self
    {
        $data = $request->validated();
        $departmentIds = isset($data['department_ids']) ? array_values($data['department_ids']) : null;
        unset($data['department_ids']);

        return new self(self::actorFrom($request), $request->member(), $data, $departmentIds);
    }
}
