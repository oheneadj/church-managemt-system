<?php

declare(strict_types=1);

use App\Contracts\DisbursementProvider;
use App\Contracts\PaymentProvider;
use App\Exceptions\PaymentProviderUnavailableException;
use App\Services\Payment\PayperlezPaymentProvider;

// Which driver collects MoMo payments and pays churches out is a config choice (D-020), never a code change.

it('collects and pays out through PayPerlez by default', function (): void {
    expect(app(PaymentProvider::class))->toBeInstanceOf(PayperlezPaymentProvider::class)
        ->and(app(DisbursementProvider::class))->toBeInstanceOf(PayperlezPaymentProvider::class);
});

it('refuses a provider that has not been built, rather than falling back to another with someone else\'s money', function (): void {
    config(['services.payment.momo_provider' => 'moolre']);

    expect(fn () => app(PaymentProvider::class))->toThrow(PaymentProviderUnavailableException::class, 'moolre');
    expect(fn () => app(DisbursementProvider::class))->toThrow(PaymentProviderUnavailableException::class);
});
