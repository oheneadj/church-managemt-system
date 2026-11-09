<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\Payments\CollectionResult;
use App\Enums\MomoNetwork;
use App\Exceptions\PaymentProviderUnavailableException;
use App\Exceptions\PaymentRejectedException;
use InvalidArgumentException;

/**
 * Collecting money from a giver (docs/09, D-020). Everything that collects money is behind this, so swapping a provider is a
 * config change and one new driver. Money is a decimal string in GHS; the provider's own shapes and error codes never leave the
 * driver.
 */
interface PaymentProvider
{
    /**
     * Starts a MoMo collection. `$clientReference` is our own id for the attempt: it is sent to the provider and doubles as the
     * key that makes a retry harmless, so asking twice never charges twice.
     *
     * @throws InvalidArgumentException when the amount is not a positive decimal
     * @throws PaymentProviderUnavailableException when trying again later could work
     * @throws PaymentRejectedException when the provider refuses for good
     */
    public function collect(string $phone, string $amountGhs, string $clientReference, MomoNetwork $network): CollectionResult;

    /**
     * Asks the provider where a collection stands, by our own reference. Used by the polling fallback for anything not yet final.
     *
     * @throws PaymentProviderUnavailableException
     * @throws PaymentRejectedException
     */
    public function collectionStatus(string $clientReference): CollectionResult;
}
