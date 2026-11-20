<?php

declare(strict_types=1);

namespace App\Actions\Contributions;

use App\DTOs\Contributions\VoidContributionDTO;
use App\Enums\AuditEvent;
use App\Exceptions\ContributionAlreadyVoidedException;
use App\Models\Contribution;
use App\Repositories\ContributionRepositoryInterface;
use App\Services\AuditLogger;
use App\Services\PledgeProgressService;
use Illuminate\Support\Facades\DB;

/**
 * Voids a gift with a reason. The gift is never deleted: it stays, marked, and drops out of every total, including
 * the pledge it paid. The row is
 * locked while it is checked and changed, so two voids at the same moment cannot both succeed (agreement section 10).
 */
final class VoidContributionAction
{
    /**
     * Takes the contribution repository, the pledge progress service and the change-history writer.
     */
    public function __construct(
        private readonly ContributionRepositoryInterface $contributions,
        private readonly PledgeProgressService $pledgeProgress,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Voids the gift as the staff member in the DTO.
     *
     * @throws ContributionAlreadyVoidedException
     */
    public function execute(VoidContributionDTO $dto): Contribution
    {
        return DB::transaction(function () use ($dto): Contribution {
            $contribution = $this->contributions->lockForUpdate($dto->contribution);

            if ($contribution->is_voided) {
                throw new ContributionAlreadyVoidedException;
            }

            $voided = $this->contributions->markVoided($contribution, $dto->actor, $dto->reason, now());

            $this->audit->record($voided, AuditEvent::Voided, $dto->actor, ['is_voided' => false], ['is_voided' => true], $dto->reason);

            // A voided gift no longer counts toward the pledge it paid, so the pledge's paid amount and status are recalculated.
            if ($voided->pledge_id !== null) {
                $this->pledgeProgress->refresh($voided->pledge_id);
            }

            return $voided;
        });
    }
}
