<?php

declare(strict_types=1);

namespace App\Actions\SmsCredits;

use App\DTOs\SmsCredits\SmsCreditChange;
use App\Enums\CreditLedgerType;
use App\Repositories\ChurchRepositoryInterface;
use App\Repositories\SmsCreditLedgerRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Takes the credits a text costs, just before it is sent (D-051). The church row is locked while the balance is checked
 * and lowered, so two texts at the same moment cannot both spend the last credit. A message that was already charged is
 * not charged again, so a retry is harmless.
 */
final class ReserveSmsCreditsAction
{
    /**
     * Takes the church and ledger repositories.
     */
    public function __construct(private readonly ChurchRepositoryInterface $churches, private readonly SmsCreditLedgerRepositoryInterface $ledger) {}

    /**
     * Returns true when the message is paid for (now or earlier), and false when the church has too few credits.
     */
    public function execute(SmsCreditChange $change): bool
    {
        return DB::transaction(function () use ($change): bool {
            $church = $this->churches->lock($change->churchId);

            if ($this->ledger->findFor($change->messageLogId, CreditLedgerType::Consumption) !== null) {
                return true;
            }

            if ($church->sms_credits < $change->credits) {
                return false;
            }

            $church->sms_credits -= $change->credits;
            $this->churches->save($church);
            $this->ledger->append([
                'church_id' => $church->id,
                'type' => CreditLedgerType::Consumption,
                'credits' => -$change->credits,
                'balance_after' => $church->sms_credits,
                'reference_id' => $change->messageLogId,
                'note' => 'Text message',
            ]);

            return true;
        });
    }
}
