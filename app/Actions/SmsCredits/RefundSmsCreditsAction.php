<?php

declare(strict_types=1);

namespace App\Actions\SmsCredits;

use App\DTOs\SmsCredits\SmsCreditChange;
use App\Enums\CreditLedgerType;
use App\Repositories\ChurchRepositoryInterface;
use App\Repositories\SmsCreditLedgerRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Gives back the credits of a text that was paid for and then failed for good (D-051). Only a message that was
 * charged can be refunded, and only once, however many times this runs.
 */
final class RefundSmsCreditsAction
{
    /**
     * Takes the church and ledger repositories.
     */
    public function __construct(private readonly ChurchRepositoryInterface $churches, private readonly SmsCreditLedgerRepositoryInterface $ledger) {}

    /**
     * Returns true when credits were given back now, and false when there was nothing to give back.
     */
    public function execute(SmsCreditChange $change): bool
    {
        return DB::transaction(function () use ($change): bool {
            $church = $this->churches->lock($change->churchId);
            $charge = $this->ledger->findFor($change->messageLogId, CreditLedgerType::Consumption);

            if ($charge === null || $this->ledger->findFor($change->messageLogId, CreditLedgerType::Refund) !== null) {
                return false;
            }

            $back = abs($charge->credits);
            $church->sms_credits += $back;
            $this->churches->save($church);
            $this->ledger->append([
                'church_id' => $church->id,
                'type' => CreditLedgerType::Refund,
                'credits' => $back,
                'balance_after' => $church->sms_credits,
                'reference_id' => $change->messageLogId,
                'note' => 'Text message not sent',
            ]);

            return true;
        });
    }
}
