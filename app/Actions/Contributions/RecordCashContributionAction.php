<?php

declare(strict_types=1);

namespace App\Actions\Contributions;

use App\DTOs\Contributions\RecordCashContributionDTO;
use App\Enums\AuditEvent;
use App\Enums\PaymentMethod;
use App\Events\ContributionReceived;
use App\Models\Contribution;
use App\Repositories\ContributionRepositoryInterface;
use App\Repositories\FundTypeRepositoryInterface;
use App\Repositories\PledgeRepositoryInterface;
use App\Services\AuditLogger;
use App\Services\PledgeProgressService;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Records a cash gift. The gift, its change-history line and the pledge it pays (when it names one) are saved together
 * or not at all. Then the gift-recorded event fires; the receipt text is sent by a queued listener after the commit. Cash has no processing
 * fee and no payment reference, because no payment provider is involved.
 */
final class RecordCashContributionAction
{
    /**
     * Takes the repositories, the pledge progress service and the change-history writer.
     */
    public function __construct(
        private readonly ContributionRepositoryInterface $contributions,
        private readonly PledgeRepositoryInterface $pledges,
        private readonly FundTypeRepositoryInterface $fundTypes,
        private readonly PledgeProgressService $pledgeProgress,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Records the gift as the staff member in the DTO.
     */
    public function execute(RecordCashContributionDTO $dto): Contribution
    {
        return DB::transaction(function () use ($dto): Contribution {
            // The pledge is locked before the gift is saved, so two gifts to one pledge take turns and each sees the other.
            if ($dto->pledgeId !== null) {
                $this->pledges->lockForUpdate($dto->pledgeId);
            }

            $contribution = $this->contributions->create([
                'church_id' => $dto->actor->church_id,
                'member_id' => $dto->memberId,
                'service_id' => $dto->serviceId,
                'pledge_id' => $dto->pledgeId,
                'fund_type_id' => $dto->fundTypeId,
                'fund_name' => $this->fundName($dto->fundTypeId),
                'amount' => $dto->amount->amount,
                'payment_method' => PaymentMethod::Cash,
                'is_voided' => false,
                'recorded_by' => $dto->actor->getKey(),
                'notes' => $dto->notes,
                'contributed_at' => $dto->contributedAt,
            ]);

            $this->audit->record($contribution, AuditEvent::Created, $dto->actor, [], [
                'amount' => $dto->amount->amount,
                'fund_type_id' => $dto->fundTypeId,
                'member_id' => $dto->memberId,
                'payment_method' => PaymentMethod::Cash->value,
                'pledge_id' => $dto->pledgeId,
            ]);

            if ($dto->pledgeId !== null) {
                $this->pledgeProgress->refresh($dto->pledgeId);
            }

            ContributionReceived::dispatch($contribution);

            return $contribution;
        });
    }

    /**
     * The fund's name as it is right now, kept on the gift so a later rename does not change what the gift was given to.
     */
    private function fundName(string $fundTypeId): string
    {
        return $this->fundTypes->find($fundTypeId)->name ?? throw new LogicException('A gift needs a fund that exists; the request checks this.');
    }
}
