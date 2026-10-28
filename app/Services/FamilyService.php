<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FamilyRole;
use App\Models\Family;
use App\Models\Member;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Families group members into households (docs/03). Linking a member to a family is done on the member,
 * so the one-head and one-family rules live in MemberService; this class only creates and renames families
 * and sets the first head.
 */
class FamilyService
{
    public function __construct(private readonly MemberService $members) {}

    /**
     * One page of families. The member count only counts members the viewer is allowed to see.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Family>
     */
    public function paginate(User $viewer, array $filters): LengthAwarePaginator
    {
        return Family::query()
            ->withCount(['members' => function (Builder $members) use ($viewer): void {
                /** @var Builder<Member> $members */
                $members->visibleTo($viewer);
            }])
            ->when(isset($filters['search']), fn (Builder $q) => $q->whereLike('name', '%'.addcslashes((string) $filters['search'], '%_\\').'%'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 25));
    }

    /**
     * Loads the family's head and the members the viewer may see.
     */
    public function withMembers(Family $family, User $viewer): Family
    {
        $family->setRelation('members', $family->members()->visibleTo($viewer)->orderBy('first_name')->orderBy('id')->get());

        return $family->load('head');
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function create(User $actor, array $data): Family
    {
        return DB::transaction(function () use ($actor, $data): Family {
            $family = Family::create([
                'church_id' => $actor->church_id,
                'name' => $data['name'],
                'notes' => $data['notes'] ?? null,
            ]);

            if (isset($data['head_member_id'])) {
                $head = Member::query()->whereKey($data['head_member_id'])->firstOrFail();

                if ($head->family_id !== null) {
                    throw ValidationException::withMessages(['head_member_id' => 'This member already belongs to a family.']);
                }

                $this->members->update($actor, $head, ['family_id' => $family->getKey(), 'family_role' => FamilyRole::Head->value]);
            }

            return $this->withMembers($family->refresh(), $actor);
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function update(User $actor, Family $family, array $data): Family
    {
        $family->update($data);

        return $this->withMembers($family, $actor);
    }
}
