<?php

declare(strict_types=1);

namespace App\Actions\Contributions;

use App\DTOs\Contributions\InitiateContributionDTO;
use App\Enums\AuditEvent;
use App\Enums\ContributionStatus;
use App\Enums\PaymentMethod;
use App\Models\Contribution;
use App\Services\AuditLogger;
use App\Services\DigitalGiftStarter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Starts a mobile money gift (D-053, D-069 step d3). It saves the gift as pending, with the processing fee the giver pays on top
 * and the total asked for, and queues the call to the payment provider, so the request never waits on the provider (agreement
 * section 15). Nothing is counted, receipted or paid out yet: that happens when the provider confirms. The gift's id is the
 * reference the provider sees, so a retry never charges twice. It is refused while the church has no payout account the provider
 * has named, because a gift that cannot be paid out must not be taken.
 */
final class InitiateContributionAction
{
    /**
     * Takes the gift starter and the change-history writer.
     */
    public function __construct(
        private readonly DigitalGiftStarter $starter,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Saves the pending gift, queues the payment once it is saved, and returns the gift.
     *
     * @throws ValidationException when the church cannot take digital gifts yet
     */
    public function execute(InitiateContributionDTO $dto): Contribution
    {
        $this->starter->assertReady($dto->church, 'Mobile money giving is not switched on yet. Add the church\'s payout account in Settings and wait until it is checked.');

        $gift = DB::transaction(function () use ($dto): Contribution {
            $gift = $this->starter->save($dto->church, $dto->fundTypeId, $dto->amount, $dto->phone, [
                'member_id' => $dto->memberId,
                'service_id' => $dto->serviceId,
                'pledge_id' => $dto->pledgeId,
                'recorded_by' => $dto->actor->getKey(),
            ]);

            $this->audit->record($gift, AuditEvent::Created, $dto->actor, [], [
                'amount' => $dto->amount->amount,
                'fund_type_id' => $dto->fundTypeId,
                'member_id' => $dto->memberId,
                'payment_method' => PaymentMethod::Momo->value,
                'status' => ContributionStatus::Pending->value,
            ]);

            return $gift;
        });

        $this->starter->queue($gift, $dto->phone, $dto->network);

        return $gift;
    }
}
