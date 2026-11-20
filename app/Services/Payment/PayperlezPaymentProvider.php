<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\DisbursementProvider;
use App\Contracts\PaymentProvider;
use App\DTOs\Payments\CollectionResult;
use App\DTOs\Payments\DisbursementResult;
use App\DTOs\Payments\PayoutAccount;
use App\Enums\MomoNetwork;
use App\Enums\PayoutMethod;
use App\Enums\ProviderPaymentStatus;
use App\Exceptions\PaymentProviderUnavailableException;
use App\Exceptions\PaymentRejectedException;
use App\ValueObjects\Money;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * MoMo collections and church payouts through PayPerlez (docs/14, section 7, D-018, D-020). It implements both provider
 * interfaces, so nothing else in the app knows PayPerlez exists.
 *
 * Amounts come in as decimal strings in GHS and go on the wire as the exact JSON number, written by hand rather than through a
 * float. Answers come back as JSON numbers, and are turned into exact two-place strings by `Money`. A retry is safe: every
 * request carries an Idempotency-Key built from our own reference, so PayPerlez returns the first answer instead of acting twice.
 * Only the status, the error code and the request id are ever logged, never a number, a name or an amount.
 */
final class PayperlezPaymentProvider implements DisbursementProvider, PaymentProvider
{
    /** Ghana's country code, which PayPerlez accepts as the leading 0 of a local number. */
    private const GHANA_PREFIX = '+233';

    /**
     * Starts a MoMo collection.
     */
    public function collect(string $phone, string $amountGhs, string $clientReference, MomoNetwork $network): CollectionResult
    {
        $body = $this->jsonBody($amountGhs, ['network' => $network->value, 'customer_msisdn' => $this->local($phone), 'client_reference' => $clientReference]);
        $response = $this->send(fn () => $this->client('collect-'.$clientReference)->withBody($body, 'application/json')->post('/collections'));

        return $this->collectionFrom($response, $clientReference, $amountGhs);
    }

    /**
     * Asks where a collection stands, by our own reference.
     */
    public function collectionStatus(string $clientReference): CollectionResult
    {
        return $this->collectionFrom($this->send(fn () => $this->client()->get('/status', ['client_reference' => $clientReference])), $clientReference, '0.00');
    }

    /**
     * Pays the church's account the most we will spend, the provider's fee included.
     */
    public function disburse(PayoutAccount $account, string $budgetGhs, string $clientReference): DisbursementResult
    {
        $body = $this->jsonBody($budgetGhs, [...$this->recipient($account), 'client_reference' => $clientReference]);
        $response = $this->send(fn () => $this->client('payout-'.$clientReference)->withBody($body, 'application/json')->post('/disbursements'));

        return $this->disbursementFrom($response, $clientReference, $budgetGhs);
    }

    /**
     * Asks where a payout stands, by PayPerlez's own reference.
     */
    public function disbursementStatus(string $providerReference): DisbursementResult
    {
        return $this->disbursementFrom($this->send(fn () => $this->client()->get('/disbursements/'.$providerReference)), '', '0.00');
    }

    /**
     * The balance that can be paid out now.
     */
    public function availableBalance(): string
    {
        $balance = $this->send(fn () => $this->client()->get('/account-balance'))->json('data.available_balance');

        return $this->decimal($balance) ?? throw new PaymentRejectedException('PayPerlez gave no balance.');
    }

    /**
     * Checks a payout account and returns the holder's name as PayPerlez knows it.
     */
    public function verifyAccount(PayoutAccount $account): string
    {
        $body = $account->method === PayoutMethod::Momo
            ? ['type' => 'momo', 'network' => $account->network?->value, 'account_number' => $this->local($account->number)]
            : ['type' => 'bank', 'bank_code' => $account->bankCode, 'account_number' => $account->number];

        $name = $this->send(fn () => $this->client()->post('/account-name', $body))->json('data.account_name');

        return is_string($name) && $name !== '' ? $name : throw new PaymentRejectedException('PayPerlez could not name the account.');
    }

    /**
     * The HTTP client with the key and the time limit already set, and the retry key when the call acts on money.
     */
    private function client(?string $idempotencyKey = null): PendingRequest
    {
        $client = Http::baseUrl((string) config('services.payperlez.base_url'))
            ->withHeaders(['X-API-Key' => (string) config('services.payperlez.api_key')])
            ->acceptJson()
            ->timeout((int) config('services.payperlez.timeout_seconds'));

        return $idempotencyKey === null ? $client : $client->withHeaders(['Idempotency-Key' => $idempotencyKey]);
    }

    /**
     * Makes the call and checks the answer: a good one is returned; a lost connection, a temporary trouble or a refusal becomes
     * our own exception.
     *
     * @param  Closure(): Response  $call
     *
     * @throws PaymentProviderUnavailableException
     * @throws PaymentRejectedException
     */
    private function send(Closure $call): Response
    {
        try {
            $response = $call();
        } catch (ConnectionException) {
            throw new PaymentProviderUnavailableException('PayPerlez could not be reached.');
        }

        if ($response->status() === HttpResponse::HTTP_TOO_MANY_REQUESTS || $response->serverError()) {
            throw new PaymentProviderUnavailableException("PayPerlez was busy or down: status {$response->status()}.");
        }

        if (! $response->successful()) {
            $code = (string) $response->json('error.code', 'unknown');
            $requestId = (string) $response->json('meta.request_id', 'none');
            Log::error('PayPerlez refused a payment request', ['status' => $response->status(), 'error_code' => $code, 'request_id' => $requestId]);

            throw new PaymentRejectedException("PayPerlez refused the request: status {$response->status()}, code {$code}, request {$requestId}.");
        }

        return $response;
    }

    /**
     * The JSON body with the amount as the exact number. The amount is checked first (a positive decimal), then written as the
     * text it is, because a float could change it. The other fields go through the normal encoder.
     *
     * @param  array<string, mixed>  $fields
     */
    private function jsonBody(string $amountGhs, array $fields): string
    {
        $amount = $this->positive($amountGhs);

        return '{"amount":'.$amount.','.substr((string) json_encode(array_filter($fields, fn (mixed $value): bool => $value !== null)), 1);
    }

    /**
     * An amount checked to be a positive decimal and written to two places.
     *
     * @throws InvalidArgumentException
     */
    private function positive(string $amountGhs): string
    {
        $amount = Money::ghs(is_numeric($amountGhs) ? $amountGhs : throw new InvalidArgumentException('An amount must be a decimal number.'))->amount;

        return bccomp($amount, '0', 2) === 1 ? $amount : throw new InvalidArgumentException('An amount must be above zero.');
    }

    /**
     * A collection from PayPerlez's answer. An answer that cannot be read is `Unknown`, so it is asked again.
     */
    private function collectionFrom(Response $response, string $clientReference, string $fallbackAmount): CollectionResult
    {
        $data = (array) $response->json('data', []);

        return new CollectionResult(
            reference: (string) ($data['reference'] ?? ''),
            clientReference: $clientReference,
            status: $this->status($data['status'] ?? null),
            amount: $this->decimal($data['amount'] ?? null) ?? Money::ghs($fallbackAmount)->amount,
            fee: $this->decimal($data['fee'] ?? null),
            received: $this->decimal($data['received'] ?? null),
        );
    }

    /**
     * A payout from PayPerlez's answer. An answer that cannot be read is `Unknown`, so it is asked again.
     */
    private function disbursementFrom(Response $response, string $clientReference, string $fallbackAmount): DisbursementResult
    {
        $data = (array) $response->json('data', []);

        return new DisbursementResult(
            reference: (string) ($data['reference'] ?? ''),
            clientReference: $clientReference !== '' ? $clientReference : (string) ($data['client_reference'] ?? ''),
            status: $this->status($data['status'] ?? null),
            amount: $this->decimal($data['amount'] ?? null) ?? Money::ghs($fallbackAmount)->amount,
            fee: $this->decimal($data['fee'] ?? null),
            received: $this->decimal($data['received'] ?? null),
        );
    }

    /**
     * PayPerlez's status in our words. Only `succeeded` is a success. Anything not recognised is `Unknown`, never assumed.
     */
    private function status(mixed $said): ProviderPaymentStatus
    {
        return match ($said) {
            'succeeded' => ProviderPaymentStatus::Succeeded,
            'pending', 'processing' => ProviderPaymentStatus::Pending,
            'failed' => ProviderPaymentStatus::Failed,
            default => ProviderPaymentStatus::Unknown,
        };
    }

    /**
     * A number from PayPerlez's JSON as an exact two-place decimal string, or null when it gave none. The JSON number is turned
     * into its shortest text (0.04 stays "0.04") and normalised by `Money`; no arithmetic is done on a float.
     */
    private function decimal(mixed $value): ?string
    {
        return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)) ? Money::ghs((string) $value)->amount : null;
    }

    /**
     * The recipient fields of a payout: a mobile money wallet or a bank account.
     *
     * @return array<string, string|null>
     */
    private function recipient(PayoutAccount $account): array
    {
        if ($account->method === PayoutMethod::Momo) {
            return ['channel' => 'mobile_money', 'recipient_name' => $account->holderName, 'recipient_msisdn' => $this->local($account->number), 'network' => $account->network?->value];
        }

        return ['channel' => 'bank_transfer', 'recipient_name' => $account->holderName, 'recipient_bank_code' => $account->bankCode, 'recipient_account_number' => $account->number];
    }

    /**
     * A Ghana number in local form (0244123456), which is the form PayPerlez documents; others stay international.
     */
    private function local(string $e164): string
    {
        return str_starts_with($e164, self::GHANA_PREFIX) ? '0'.substr($e164, strlen(self::GHANA_PREFIX)) : $e164;
    }
}
