<?php

declare(strict_types=1);

namespace App\Actions\Contributions;

use App\Contracts\DisbursementProvider;
use App\Enums\DisbursementStatus;
use App\Exceptions\PaymentRejectedException;
use App\Models\Disbursement;
use App\Repositories\DisbursementRepositoryInterface;
use App\Services\DisbursementOutcome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Asks the provider where a payout it has taken stands and records the answer (D-018, D-069 step e4; agreement sections 10 and 15: the
 * polling fallback). A payout the provider answered "still working" is asked about again until it is paid or failed. The answer goes through
 * the same rules as when the payout was sent (DisbursementOutcome), under a lock, so a payout is paid once however many answers arrive and a
 * paid payout is never changed. A provider that will not say is logged and the payout is left for the next round.
 */
final class ConfirmDisbursementAction
{
    /**
     * Takes the repository, the provider and the outcome rules.
     */
    public function __construct(
        private readonly DisbursementRepositoryInterface $disbursements,
        private readonly DisbursementProvider $provider,
        private readonly DisbursementOutcome $outcomes,
    ) {}

    /**
     * Asks about the payout and records the answer, and returns the payout as it now stands, or null when there is no such payout.
     */
    public function execute(string $disbursementId): ?Disbursement
    {
        $disbursement = $this->disbursements->find($disbursementId);

        if ($disbursement === null || ! $this->isWaitingForTheProvider($disbursement)) {
            return $disbursement;
        }

        try {
            $result = $this->provider->disbursementStatus((string) $disbursement->provider_reference);
        } catch (PaymentRejectedException) {
            Log::warning('The provider would not say where a payout stands', ['disbursement_id' => $disbursement->id, 'church_id' => $disbursement->church_id]);

            return $disbursement;
        }

        return DB::transaction(function () use ($disbursementId, $result): ?Disbursement {
            $locked = $this->disbursements->lockById($disbursementId);

            if ($locked === null || ! $this->isWaitingForTheProvider($locked)) {
                return $locked;
            }

            return $this->outcomes->apply($locked, $result);
        });
    }

    /**
     * Whether the provider has taken this payout and not finished with it: processing, with a provider reference, and not a gift inside a
     * batch (the batch is the payout that is asked about).
     */
    private function isWaitingForTheProvider(Disbursement $disbursement): bool
    {
        return $disbursement->status === DisbursementStatus::Processing && $disbursement->provider_reference !== null && $disbursement->batch_id === null;
    }
}
