<?php

declare(strict_types=1);

use App\Contracts\DisbursementProvider;
use App\Contracts\PaymentProvider;
use App\DTOs\Payments\PayoutAccount;
use App\Enums\MomoNetwork;
use App\Enums\ProviderPaymentStatus;
use App\Exceptions\PaymentProviderUnavailableException;
use App\Exceptions\PaymentRejectedException;
use App\Services\Payment\PayperlezPaymentProvider;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// PayPerlez collections, payouts, balance and account checks (docs/14 section 7, verified against biz.payperlez.org, Oct 2026).
// Every call is faked: no real money ever moves from the tests. Money crosses the interface as a decimal string in GHS (D-020)
// and goes on the wire as the exact number, never through a float.

const PPZ_BASE = 'https://api.payperlez.org/api/v1';

beforeEach(function (): void {
    config(['services.payperlez.api_key' => 'ppz_test_key', 'services.payperlez.base_url' => PPZ_BASE]);
    $this->provider = new PayperlezPaymentProvider;
});

/** A PayPerlez reply for a collection or a payout. */
function ppzObject(array $data = [], int $status = 201): Illuminate\Http\Client\PromiseInterface|PromiseInterface
{
    return Http::response(['success' => true, 'data' => [...[
        'reference' => 'ppz-ref-1', 'status' => 'succeeded', 'amount' => 200.0, 'fee' => 0.8, 'received' => 200.0, 'client_reference' => 'attempt-1', 'is_test' => true,
    ], ...$data], 'meta' => ['request_id' => 'req-1']], $status);
}

it('is both a payment provider and a disbursement provider', function (): void {
    expect($this->provider)->toBeInstanceOf(PaymentProvider::class)->toBeInstanceOf(DisbursementProvider::class);
});

describe('collecting from a giver', function (): void {
    it('posts the collection with the key, a retry key built from our reference, and the exact amount as a number', function (): void {
        Http::fake([PPZ_BASE.'/collections' => ppzObject(['status' => 'pending', 'fee' => null])]);

        $result = $this->provider->collect('+233244123456', '200.00', 'attempt-1', MomoNetwork::Mtn);

        Http::assertSent(function (Request $request): bool {
            $body = $request->body();

            return $request->url() === PPZ_BASE.'/collections'
                && $request->method() === 'POST'
                && $request->header('X-API-Key') === ['ppz_test_key']
                && $request->header('Idempotency-Key') === ['collect-attempt-1']
                // The amount is a JSON number written exactly as the decimal string, so no float ever touches it.
                && str_contains($body, '"amount":200.00')
                && $request['network'] === 'mtn'
                && $request['customer_msisdn'] === '0244123456'
                && $request['client_reference'] === 'attempt-1';
        });
        expect($result->reference)->toBe('ppz-ref-1')->and($result->clientReference)->toBe('attempt-1')->and($result->status)->toBe(ProviderPaymentStatus::Pending);
    });

    it('writes an amount exactly, whatever its decimals', function (string $sent, string $onTheWire): void {
        Http::fake([PPZ_BASE.'/collections' => ppzObject()]);

        $this->provider->collect('+233244123456', $sent, 'attempt-1', MomoNetwork::Telecel);

        Http::assertSent(fn (Request $request): bool => str_contains($request->body(), '"amount":'.$onTheWire.','));
    })->with([['1.00', '1.00'], ['0.10', '0.10'], ['30000.00', '30000.00'], ['7', '7.00'], ['1234.5', '1234.50']]);

    it('reads the fee and the amount received as exact decimals, and a missing fee as nothing', function (): void {
        Http::fake([PPZ_BASE.'/collections' => ppzObject(['amount' => 5.0, 'fee' => 0.04, 'received' => 5.0])]);

        $result = $this->provider->collect('+233244123456', '5.00', 'attempt-1', MomoNetwork::At);

        expect($result->amount)->toBe('5.00')->and($result->fee)->toBe('0.04')->and($result->received)->toBe('5.00');
    });

    it('reads a fee and an amount received that are not known yet as nothing, not as zero', function (): void {
        Http::fake([PPZ_BASE.'/collections' => ppzObject(['status' => 'pending', 'fee' => null, 'received' => null])]);

        $pending = $this->provider->collect('+233244123456', '5.00', 'attempt-2', MomoNetwork::At);

        expect($pending->fee)->toBeNull()->and($pending->received)->toBeNull();
    });

    it('turns every status the provider can say into one of ours, and anything unknown into unknown, never a success', function (string $said, ProviderPaymentStatus $ours): void {
        Http::fake([PPZ_BASE.'/collections' => ppzObject(['status' => $said])]);

        expect($this->provider->collect('+233244123456', '5.00', 'attempt-1', MomoNetwork::Mtn)->status)->toBe($ours);
    })->with([
        ['succeeded', ProviderPaymentStatus::Succeeded],
        ['pending', ProviderPaymentStatus::Pending],
        ['processing', ProviderPaymentStatus::Pending],
        ['unknown', ProviderPaymentStatus::Unknown],
        ['failed', ProviderPaymentStatus::Failed],
        ['a status nobody has seen', ProviderPaymentStatus::Unknown],
    ]);

    it('refuses an amount that is not a positive decimal before calling anyone', function (string $bad): void {
        Http::fake();

        expect(fn () => $this->provider->collect('+233244123456', $bad, 'attempt-1', MomoNetwork::Mtn))->toThrow(InvalidArgumentException::class);

        Http::assertNothingSent();
    })->with(['zero' => '0.00', 'negative' => '-5.00', 'text' => 'ten', 'empty' => '']);
});

describe('reading a collection\'s state', function (): void {
    it('asks by our own reference and reads the answer the same way', function (): void {
        Http::fake([PPZ_BASE.'/status*' => ppzObject(['status' => 'succeeded'], 200)]);

        $result = $this->provider->collectionStatus('attempt-1');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && str_starts_with($request->url(), PPZ_BASE.'/status') && $request['client_reference'] === 'attempt-1');
        expect($result->status)->toBe(ProviderPaymentStatus::Succeeded)->and($result->reference)->toBe('ppz-ref-1');
    });

    it('says unknown, to be polled again, when the answer cannot be read', function (): void {
        Http::fake([PPZ_BASE.'/status*' => Http::response(['success' => true], 200)]);

        $result = $this->provider->collectionStatus('attempt-1');

        expect($result->status)->toBe(ProviderPaymentStatus::Unknown)->and($result->reference)->toBe('')->and($result->clientReference)->toBe('attempt-1');
    });
});

describe('paying a church', function (): void {
    it('pays a MoMo wallet with the most we will spend, the name and the network, and a retry key built from our reference', function (): void {
        Http::fake([PPZ_BASE.'/disbursements' => ppzObject(['amount' => 187.5, 'fee' => 0.07, 'received' => 187.43, 'client_reference' => 'payout-1'])]);

        $result = $this->provider->disburse(PayoutAccount::momo('Grace Assembly', '+233244123456', MomoNetwork::Mtn), '187.50', 'payout-1');

        Http::assertSent(fn (Request $request): bool => $request->url() === PPZ_BASE.'/disbursements'
            && $request->header('Idempotency-Key') === ['payout-payout-1']
            && str_contains($request->body(), '"amount":187.50,')
            && $request['channel'] === 'mobile_money'
            && $request['recipient_name'] === 'Grace Assembly'
            && $request['recipient_msisdn'] === '0244123456'
            && $request['network'] === 'mtn'
            && $request['client_reference'] === 'payout-1');
        expect($result->amount)->toBe('187.50')->and($result->fee)->toBe('0.07')->and($result->received)->toBe('187.43')->and($result->status)->toBe(ProviderPaymentStatus::Succeeded);
    });

    it('pays a bank account with the bank code and the account number, never a name alone', function (): void {
        Http::fake([PPZ_BASE.'/disbursements' => ppzObject(['status' => 'pending'], 202)]);

        $result = $this->provider->disburse(PayoutAccount::bank('Grace Assembly', '0123456789', '300302'), '50.00', 'payout-2');

        Http::assertSent(fn (Request $request): bool => $request['channel'] === 'bank_transfer'
            && $request['recipient_bank_code'] === '300302'
            && $request['recipient_account_number'] === '0123456789'
            && ! isset($request['recipient_msisdn']));
        expect($result->status)->toBe(ProviderPaymentStatus::Pending);
    });

    it('reads a payout\'s state by the provider\'s reference', function (): void {
        Http::fake([PPZ_BASE.'/disbursements/ppz-ref-9' => ppzObject(['reference' => 'ppz-ref-9', 'status' => 'failed'], 200)]);

        $result = $this->provider->disbursementStatus('ppz-ref-9');

        expect($result->status)->toBe(ProviderPaymentStatus::Failed)->and($result->reference)->toBe('ppz-ref-9');
    });

    it('refuses a payout that is not a positive decimal before calling anyone', function (): void {
        Http::fake();

        expect(fn () => $this->provider->disburse(PayoutAccount::momo('A', '+233244123456', MomoNetwork::Mtn), '0.00', 'payout-3'))->toThrow(InvalidArgumentException::class);

        Http::assertNothingSent();
    });
});

describe('the balance and checking an account', function (): void {
    it('reads the available balance as an exact decimal string', function (): void {
        Http::fake([PPZ_BASE.'/account-balance' => Http::response(['success' => true, 'data' => ['available_balance' => 50901.5, 'currency' => 'GHS']], 200)]);

        expect($this->provider->availableBalance())->toBe('50901.50');
        Http::assertSent(fn (Request $request): bool => $request->header('X-API-Key') === ['ppz_test_key'] && $request->method() === 'GET');
    });

    it('refuses a balance the provider did not give, rather than guessing zero', function (): void {
        Http::fake([PPZ_BASE.'/account-balance' => Http::response(['success' => true, 'data' => []], 200)]);

        expect(fn () => $this->provider->availableBalance())->toThrow(PaymentRejectedException::class);
    });

    it('reads amounts the provider sends as text as well as numbers', function (): void {
        Http::fake([PPZ_BASE.'/disbursements' => ppzObject(['amount' => '12.5', 'fee' => '0.07', 'received' => '12.43'])]);

        $result = $this->provider->disburse(PayoutAccount::momo('A', '+233244123456', MomoNetwork::Mtn), '12.50', 'payout-1');

        expect($result->amount)->toBe('12.50')->and($result->fee)->toBe('0.07')->and($result->received)->toBe('12.43');
    });

    it('verifies a MoMo account and returns the holder\'s name', function (): void {
        Http::fake([PPZ_BASE.'/account-name' => Http::response(['success' => true, 'data' => ['account_name' => 'Grace Assembly Ltd']], 200)]);

        $name = $this->provider->verifyAccount(PayoutAccount::momo('Grace', '+233244123456', MomoNetwork::Telecel));

        expect($name)->toBe('Grace Assembly Ltd');
        Http::assertSent(fn (Request $request): bool => $request['type'] === 'momo' && $request['network'] === 'telecel' && $request['account_number'] === '0244123456');
    });

    it('verifies a bank account with its bank code', function (): void {
        Http::fake([PPZ_BASE.'/account-name' => Http::response(['success' => true, 'data' => ['account_name' => 'Grace Assembly Ltd']], 200)]);

        $this->provider->verifyAccount(PayoutAccount::bank('Grace', '0123456789', '300302'));

        Http::assertSent(fn (Request $request): bool => $request['type'] === 'bank' && $request['bank_code'] === '300302' && $request['account_number'] === '0123456789');
    });

    it('refuses an account the provider could not name', function (): void {
        Http::fake([PPZ_BASE.'/account-name' => Http::response(['success' => true, 'data' => []], 200)]);

        expect(fn () => $this->provider->verifyAccount(PayoutAccount::momo('Grace', '+233244123456', MomoNetwork::Mtn)))->toThrow(PaymentRejectedException::class);
    });
});

describe('when PayPerlez cannot or will not', function (): void {
    it('treats a rate limit and a server error as temporary, so the job tries again later', function (int $status): void {
        Http::fake([PPZ_BASE.'/collections' => Http::response(['success' => false], $status)]);

        expect(fn () => $this->provider->collect('+233244123456', '5.00', 'attempt-1', MomoNetwork::Mtn))->toThrow(PaymentProviderUnavailableException::class);
    })->with([429, 500, 503]);

    it('treats a lost connection as temporary, whichever call it happens on', function (): void {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        expect(fn () => $this->provider->collect('+233244123456', '5.00', 'attempt-1', MomoNetwork::Mtn))->toThrow(PaymentProviderUnavailableException::class);
        expect(fn () => $this->provider->availableBalance())->toThrow(PaymentProviderUnavailableException::class);
        expect(fn () => $this->provider->disbursementStatus('ppz-ref-1'))->toThrow(PaymentProviderUnavailableException::class);
    });

    it('treats a refusal as final, and logs only the status, the error code and the request id', function (): void {
        Log::spy();
        Http::fake([PPZ_BASE.'/collections' => Http::response(['success' => false, 'error' => ['code' => 'validation_error', 'message' => 'bad number 0244123456'], 'meta' => ['request_id' => 'req-77']], 422)]);

        expect(fn () => $this->provider->collect('+233244123456', '5.00', 'attempt-1', MomoNetwork::Mtn))->toThrow(PaymentRejectedException::class, 'validation_error');

        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context): bool => $context === ['status' => 422, 'error_code' => 'validation_error', 'request_id' => 'req-77']);
    });

    it('treats a retry with a different body (409) as a refusal, not something to retry', function (): void {
        Http::fake([PPZ_BASE.'/disbursements' => Http::response(['success' => false, 'error' => ['code' => 'idempotency_conflict']], 409)]);

        expect(fn () => $this->provider->disburse(PayoutAccount::momo('A', '+233244123456', MomoNetwork::Mtn), '5.00', 'payout-1'))->toThrow(PaymentRejectedException::class);
    });

    it('treats a missing key as a refusal of every call', function (): void {
        Http::fake([PPZ_BASE.'/*' => Http::response(['success' => false, 'error' => ['code' => 'unauthorized']], 401)]);

        expect(fn () => $this->provider->availableBalance())->toThrow(PaymentRejectedException::class);
        expect(fn () => $this->provider->collectionStatus('attempt-1'))->toThrow(PaymentRejectedException::class);
        expect(fn () => $this->provider->disbursementStatus('ppz-ref-1'))->toThrow(PaymentRejectedException::class);
    });
});
