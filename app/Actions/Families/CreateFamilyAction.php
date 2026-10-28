<?php

declare(strict_types=1);

namespace App\Actions\Families;

use App\DTOs\Families\CreateFamilyDTO;
use App\Enums\FamilyRole;
use App\Events\MemberUpdated;
use App\Models\Family;
use App\Repositories\FamilyRepositoryInterface;
use App\Repositories\MemberRepositoryInterface;
use App\Services\MemberFamilyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates a family, and when a first head is given, makes that member the head and fires MemberUpdated for
 * them. A member who already belongs to a family is never moved silently.
 */
final class CreateFamilyAction
{
    public function __construct(
        private readonly FamilyRepositoryInterface $families,
        private readonly MemberRepositoryInterface $members,
        private readonly MemberFamilyService $memberFamilies,
    ) {}

    public function execute(CreateFamilyDTO $dto): Family
    {
        return DB::transaction(function () use ($dto): Family {
            $family = $this->families->create([
                'church_id' => $dto->actor->church_id,
                'name' => $dto->name,
                'notes' => $dto->notes,
            ]);

            if ($dto->headMemberId !== null) {
                $head = $this->members->findOrFail($dto->headMemberId);

                if ($head->family_id !== null) {
                    throw ValidationException::withMessages(['head_member_id' => 'This member already belongs to a family.']);
                }

                [$old, $new] = $this->memberFamilies->assign($head, $family->getKey(), FamilyRole::Head);

                MemberUpdated::dispatch($head, $dto->actor, $old, $new);

                // The head was set by a separate update, so the family in memory is stale.
                $family = $this->families->fresh($family);
            }

            return $this->families->loadWithVisibleMembers($family, $dto->actor);
        });
    }
}
