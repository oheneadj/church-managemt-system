<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FamilyRole;
use App\Models\Member;
use App\Repositories\FamilyRepositoryInterface;
use App\Repositories\MemberRepositoryInterface;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * The family rules shared by every Action that puts a member in a family (docs/03): a role needs a family,
 * a family has one head, and the family row remembers who the head is.
 * It does not open a transaction itself: the Action owns it, and the family lock lasts until that ends.
 */
class MemberFamilyService
{
    /**
     * Takes the family repository and the member repository.
     */
    public function __construct(
        private readonly FamilyRepositoryInterface $families,
        private readonly MemberRepositoryInterface $members,
    ) {}

    /**
     * Refuses a family role with no family, and a second head. The family row is locked first, so two requests
     * that both try to become head cannot both pass the check.
     */
    public function guard(Member $member): void
    {
        if ($member->family_id === null && $member->family_role !== null) {
            throw ValidationException::withMessages(['family_id' => 'Choose a family before giving a role in it.']);
        }

        if ($member->family_id === null || $member->family_role !== FamilyRole::Head) {
            return;
        }

        $this->families->lock($member->family_id);

        if ($this->members->familyHasOtherHead($member->family_id, $member->exists ? $member->getKey() : null)) {
            throw ValidationException::withMessages(['family_role' => 'This family already has a head of household.']);
        }
    }

    /** Keeps the family's head in step with the member who holds the head role. */
    public function claimHead(Member $member): void
    {
        if ($member->family_id !== null && $member->family_role === FamilyRole::Head) {
            $this->families->setHead($member->family_id, $member->getKey());
        }
    }

    /**
     * Puts an existing member in a family with a role, and returns the old and new values of what changed.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function assign(Member $member, string $familyId, FamilyRole $role): array
    {
        $member->fill(['family_id' => $familyId, 'family_role' => $role]);
        $changed = $member->getDirty();
        $old = Arr::only($member->getOriginal(), array_keys($changed));

        $this->guard($member);
        $this->members->save($member);
        $this->claimHead($member);

        return [$old, $changed];
    }
}
