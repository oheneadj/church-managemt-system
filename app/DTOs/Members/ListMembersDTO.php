<?php

declare(strict_types=1);

namespace App\DTOs\Members;

use App\DTOs\Concerns\ResolvesActor;
use App\Enums\EducationalLevel;
use App\Enums\Gender;
use App\Enums\MemberStatus;
use App\Http\Requests\Members\ListMembersRequest;
use App\Models\User;

/**
 * What the directory list is asked for: the viewer, the filters, the sort and the page size.
 */
final readonly class ListMembersDTO
{
    use ResolvesActor;

    public function __construct(
        public User $viewer,
        public ?MemberStatus $status = null,
        public ?Gender $gender = null,
        public ?EducationalLevel $educationalLevel = null,
        public ?string $departmentId = null,
        public ?string $familyId = null,
        public ?string $search = null,
        public string $sort = 'last_name',
        public string $direction = 'asc',
        public int $perPage = 25,
    ) {}

    public static function fromRequest(ListMembersRequest $request): self
    {
        $data = $request->validated();

        return new self(
            viewer: self::actorFrom($request),
            status: isset($data['status']) ? MemberStatus::from($data['status']) : null,
            gender: isset($data['gender']) ? Gender::from($data['gender']) : null,
            educationalLevel: isset($data['educational_level']) ? EducationalLevel::from($data['educational_level']) : null,
            departmentId: $data['department_id'] ?? null,
            familyId: $data['family_id'] ?? null,
            search: $data['search'] ?? null,
            sort: $data['sort'] ?? 'last_name',
            direction: $data['direction'] ?? 'asc',
            perPage: (int) ($data['per_page'] ?? 25),
        );
    }
}
