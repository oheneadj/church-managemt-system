<?php

declare(strict_types=1);

namespace App\Actions\Members;

use App\DTOs\Members\EraseMemberDTO;
use App\Events\MemberErased;
use App\Services\MemberErasureService;
use Illuminate\Support\Facades\DB;

/**
 * Erases a member's personal data at their request and records in the change log who did it and why.
 * The erasure and the log line succeed together or not at all.
 */
final class EraseMemberAction
{
    /**
     * Takes the erasure service.
     */
    public function __construct(private readonly MemberErasureService $erasure) {}

    /**
     * Erases the member, then records it. The history is masked first so the new line is not masked with it.
     */
    public function execute(EraseMemberDTO $dto): void
    {
        DB::transaction(function () use ($dto): void {
            $this->erasure->erase($dto->member);
            MemberErased::dispatch($dto->member, $dto->actor, $dto->reason);
        });
    }
}
