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

    /**
     * Holds the values this operation needs.
     */
    public function __construct(
        public User $viewer,
        public int $perPage,
        public ?MemberStatus $status = null,
        public ?Gender $gender = null,
        public ?EducationalLevel $educationalLevel = null,
        public ?string $departmentId = null,
        public ?string $familyId = null,
        public ?string $search = null,
        public string $sort = 'last_name',
        public string $direction = 'asc',
    ) {}

    /**
     * Builds the DTO from filters kept as plain text (an export remembers the filters it was asked for), for the staff member
     * who asked. The order is the list's default: last name, then first name.
     *
     * @param  array<string, string>  $filters
     */
    public static function fromFilters(User $viewer, array $filters, int $perPage): self
    {
        return new self(
            viewer: $viewer,
            perPage: $perPage,
            status: isset($filters['status']) ? MemberStatus::from($filters['status']) : null,
            gender: isset($filters['gender']) ? Gender::from($filters['gender']) : null,
            educationalLevel: isset($filters['educational_level']) ? EducationalLevel::from($filters['educational_level']) : null,
            departmentId: $filters['department_id'] ?? null,
            familyId: $filters['family_id'] ?? null,
            search: $filters['search'] ?? null,
        );
    }

    /**
     * Builds the DTO from the validated request.
     */
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
            perPage: (int) ($data['per_page'] ?? config('limits.per_page_default')),
        );
    }
}
