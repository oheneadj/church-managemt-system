<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\Payments\DisbursementResult;
use App\DTOs\Payments\PayoutAccount;
use App\Exceptions\PaymentProviderUnavailableException;
use App\Exceptions\PaymentRejectedException;
use InvalidArgumentException;

/**
 * Paying a church its money (docs/09, D-018). Money is a decimal string in GHS; the provider's own shapes and error codes never
 * leave the driver.
 */
interface DisbursementProvider
{
    /**
     * Pays the most we will spend, the provider's fee included, to the church's payout account. `$clientReference` is our own id
     * for this one payout and doubles as the key that makes a retry harmless: asking again with the same reference and amount
     * returns the same payout, never a second one.
     *
     * @throws InvalidArgumentException when the amount is not a positive decimal
     * @throws PaymentProviderUnavailableException
     * @throws PaymentRejectedException
     */
    public function disburse(PayoutAccount $account, string $budgetGhs, string $clientReference): DisbursementResult;

    /**
     * Asks the provider where a payout stands, by the provider's own reference.
     *
     * @throws PaymentProviderUnavailableException
     * @throws PaymentRejectedException
     */
    public function disbursementStatus(string $providerReference): DisbursementResult;

    /**
     * The balance that can be paid out now, as a decimal string. The payout job checks it, because a minimum must stay in the
     * account (D-018).
     *
     * @throws PaymentProviderUnavailableException
     * @throws PaymentRejectedException
     */
    public function availableBalance(): string;

    /**
     * Checks a payout account with the provider and returns the holder's name as the provider knows it.
     *
     * @throws PaymentProviderUnavailableException
     * @throws PaymentRejectedException when the account cannot be named
     */
    public function verifyAccount(PayoutAccount $account): string;
}
