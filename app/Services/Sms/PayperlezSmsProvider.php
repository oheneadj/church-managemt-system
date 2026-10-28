<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Contracts\SmsProvider;
use App\DTOs\OutboundSms;
use App\DTOs\SmsReceipt;
use App\Exceptions\SmsRejectedException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Sends text messages through PayPerlez (docs/14, section 7): POST /sms/messages with the API key, an
 * approved sender id and an Idempotency-Key, so a retried job can never send the same text twice.
 * A 202 means the message was queued at PayPerlez, not that it was delivered.
 */
final class PayperlezSmsProvider implements SmsProvider
{
    /** Ghana's country code, which PayPerlez accepts as the leading 0 of a local number. */
    private const GHANA_PREFIX = '+233';

    /**
     * Sends one message and returns PayPerlez's reference for it.
     *
     * @throws SmsRejectedException when PayPerlez refuses the message for good
     * @throws RequestException when the failure is temporary (network, rate limit, PayPerlez down), so the job retries
     */
    public function send(OutboundSms $sms, string $idempotencyKey): SmsReceipt
    {
        $response = $this->client($idempotencyKey)->post('/sms/messages', [
            'sender_id' => config('services.payperlez.sender_id'),
            'recipient' => $this->recipient($sms->phone),
            'message' => $sms->message,
        ]);

        if ($this->isTemporary($response)) {
            $response->throw();
        }

        if (! $response->successful()) {
            $this->reject($response);
        }

        return new SmsReceipt(
            reference: $response->json('data.reference'),
            response: $this->safeParts($response),
        );
    }

    /**
     * The HTTP client with the key, the retry key and the time limit already set.
     */
    private function client(string $idempotencyKey): PendingRequest
    {
        return Http::baseUrl((string) config('services.payperlez.base_url'))
            ->withHeaders(['X-API-Key' => (string) config('services.payperlez.api_key'), 'Idempotency-Key' => $idempotencyKey])
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.payperlez.timeout_seconds'));
    }

    /**
     * A Ghana number in local form (0244123456), which is the form PayPerlez documents; others stay international.
     */
    private function recipient(string $e164): string
    {
        return str_starts_with($e164, self::GHANA_PREFIX) ? '0'.substr($e164, strlen(self::GHANA_PREFIX)) : $e164;
    }

    /**
     * Whether trying again later could work: the rate limit was hit, or PayPerlez had a problem of its own.
     */
    private function isTemporary(Response $response): bool
    {
        return $response->status() === HttpResponse::HTTP_TOO_MANY_REQUESTS || $response->serverError();
    }

    /**
     * Logs why PayPerlez refused (its error code and request id only) and stops the job from retrying.
     *
     * @throws SmsRejectedException
     */
    private function reject(Response $response): never
    {
        $code = (string) $response->json('error.code', 'unknown');
        $requestId = (string) $response->json('meta.request_id', 'none');

        Log::error('PayPerlez refused an SMS', ['status' => $response->status(), 'error_code' => $code, 'request_id' => $requestId]);

        throw new SmsRejectedException("PayPerlez refused the message: status {$response->status()}, code {$code}, request {$requestId}.");
    }

    /**
     * The parts of the reply worth keeping in the message log. The recipient and the text are left out.
     *
     * @return array<string, mixed>
     */
    private function safeParts(Response $response): array
    {
        return [
            'status' => $response->json('data.status'),
            'segments' => $response->json('data.segments_per_recipient'),
            'credits' => $response->json('data.credits'),
            'encoding' => $response->json('data.encoding'),
            'is_test' => $response->json('data.is_test'),
            'idempotent_replay' => $response->json('data.idempotent_replay'),
            'request_id' => $response->json('meta.request_id'),
        ];
    }
}
