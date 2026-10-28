<?php

declare(strict_types=1);

namespace App\Actions\Contributions;

use App\DTOs\Contributions\RecordCashContributionDTO;
use App\Enums\AuditEvent;
use App\Enums\PaymentMethod;
use App\Models\Contribution;
use App\Repositories\ContributionRepositoryInterface;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Records a cash gift. The gift and its change-history line are saved together or not at all. Cash has no processing
 * fee and no payment reference, because no payment provider is involved.
 */
final class RecordCashContributionAction
{
    /**
     * Takes the contribution repository and the change-history writer.
     */
    public function __construct(
        private readonly ContributionRepositoryInterface $contributions,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Records the gift as the staff member in the DTO.
     */
    public function execute(RecordCashContributionDTO $dto): Contribution
    {
        return DB::transaction(function () use ($dto): Contribution {
            $contribution = $this->contributions->create([
                'church_id' => $dto->actor->church_id,
                'member_id' => $dto->memberId,
                'service_id' => $dto->serviceId,
                'fund_type_id' => $dto->fundTypeId,
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
            ]);

            return $contribution;
        });
    }
}
