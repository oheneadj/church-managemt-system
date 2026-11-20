<?php

declare(strict_types=1);

namespace App\Actions\Pledges;

use App\DTOs\Pledges\CreatePledgeDTO;
use App\Enums\AuditEvent;
use App\Enums\PledgeStatus;
use App\Models\Pledge;
use App\Repositories\PledgeRepositoryInterface;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Records a member's pledge. It starts active with nothing paid, and its change-history line is saved with it.
 */
final class CreatePledgeAction
{
    /**
     * Takes the pledge repository and the change-history writer.
     */
    public function __construct(private readonly PledgeRepositoryInterface $pledges, private readonly AuditLogger $audit) {}

    /**
     * Records the pledge as the staff member in the DTO.
     */
    public function execute(CreatePledgeDTO $dto): Pledge
    {
        return DB::transaction(function () use ($dto): Pledge {
            $pledge = $this->pledges->create([
                'church_id' => $dto->actor->church_id,
                'member_id' => $dto->memberId,
                'fund_type_id' => $dto->fundTypeId,
                'description' => $dto->description,
                'amount_pledged' => $dto->amountPledged->amount,
                'amount_paid' => '0.00',
                'target_date' => $dto->targetDate,
                'status' => PledgeStatus::Active,
                'created_by' => $dto->actor->getKey(),
            ]);

            $this->audit->record($pledge, AuditEvent::Created, $dto->actor, [], [
                'member_id' => $dto->memberId,
                'fund_type_id' => $dto->fundTypeId,
                'amount_pledged' => $dto->amountPledged->amount,
                'target_date' => $dto->targetDate->toDateString(),
            ]);

            return $pledge;
        });
    }
}
