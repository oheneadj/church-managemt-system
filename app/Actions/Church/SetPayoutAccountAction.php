<?php

declare(strict_types=1);

namespace App\Actions\Church;

use App\DTOs\Church\SetPayoutAccountDTO;
use App\Enums\QueueName;
use App\Events\ChurchUpdated;
use App\Jobs\VerifyPayoutAccountJob;
use App\Models\Church;
use App\Repositories\ChurchRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Saves where the church's digital gifts are to be paid out and asks the provider, on a worker, to name the account (story 4.1,
 * D-069 step d2). A new or changed account is not trusted until the provider has named it, so the earlier name and check are
 * cleared and digital giving stays off until it passes. Saving the same account again changes nothing and checks nothing. The
 * history records that the account changed, never the number itself.
 */
final class SetPayoutAccountAction
{
    /**
     * Takes the church repository.
     */
    public function __construct(private readonly ChurchRepositoryInterface $churches) {}

    /**
     * Saves the account, queues the check and returns the church.
     */
    public function execute(SetPayoutAccountDTO $dto): Church
    {
        $church = DB::transaction(function () use ($dto): ?Church {
            $church = $this->churches->lock($dto->church->id);

            if ($this->isSame($church, $dto)) {
                return null;
            }

            $old = ['payout_account' => $church->payoutStatus() === 'not_set' ? null : $this->masked($church->payout_account_number)];
            $church->payout_method = $dto->method;
            $church->payout_network = $dto->network;
            $church->payout_bank_code = $dto->bankCode;
            $church->payout_account_number = $dto->number;
            $church->payout_account_name = null;
            $church->payout_verified_at = null;
            $church->payout_check_error = null;
            $this->churches->save($church);
            ChurchUpdated::dispatch($church, $dto->actor, $old, ['payout_account' => $this->masked($dto->number)]);

            return $church;
        });

        if ($church === null) {
            return $dto->church->refresh();
        }

        VerifyPayoutAccountJob::dispatch($church->id, $dto->number)->onQueue(QueueName::Payments->value)->afterCommit();

        return $church;
    }

    /**
     * Whether the church already has exactly this account.
     */
    private function isSame(Church $church, SetPayoutAccountDTO $dto): bool
    {
        return $church->payout_method === $dto->method
            && $church->payout_account_number === $dto->number
            && $church->payout_network === $dto->network
            && $church->payout_bank_code === $dto->bankCode;
    }

    /**
     * The number with all but its last three digits hidden, for the history.
     */
    private function masked(?string $number): ?string
    {
        return $number === null ? null : '***'.substr($number, -3);
    }
}
