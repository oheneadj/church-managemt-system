<?php

declare(strict_types=1);

use App\Contracts\SmsProvider;
use App\DTOs\OutboundSms;
use App\Enums\RecipientType;
use App\Exceptions\SmsProviderUnavailableException;
use App\Exceptions\SmsRejectedException;
use App\Services\Sms\PayperlezSmsProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// PayPerlez SMS (docs/14 section 7, verified against biz.payperlez.org/docs/sms, Oct 2026). Every call is faked;
// no real message is ever sent from the tests.

const SMS_URL = 'https://api.payperlez.org/api/v1/sms/messages';

beforeEach(function (): void {
    config([
        'services.sms.primary' => 'payperlez',
        'services.payperlez.api_key' => 'ppz_test_key',
        'services.payperlez.sender_id' => '11111111-1111-4111-8111-111111111111',
    ]);
    $this->sms = new OutboundSms('01church', '+233244123456', 'Your code is 123456', 'otp', RecipientType::User, null, '[otp redacted]');
});

/** A PayPerlez 202 reply for a queued message. */
function accepted(): array
{
    return ['success' => true, 'data' => [
        'reference' => 'ref-abc', 'status' => 'queued', 'recipient' => '024****210', 'segments_per_recipient' => 1,
        'credits' => 1, 'encoding' => 'GSM-7', 'is_test' => true, 'idempotent_replay' => false, 'message' => 'Your code is 123456',
    ], 'meta' => ['request_id' => 'req-1']];
}

it('posts the message with the key, the sender id and a retry key, and returns the reference', function (): void {
    Http::fake([SMS_URL => Http::response(accepted(), 202)]);

    $receipt = (new PayperlezSmsProvider)->send($this->sms, 'sms-retry-key-1');

    expect($receipt->reference)->toBe('ref-abc');
    Http::assertSent(fn (Request $request): bool => $request->url() === SMS_URL
        && $request->method() === 'POST'
        && $request->header('X-API-Key') === ['ppz_test_key']
        && $request->header('Idempotency-Key') === ['sms-retry-key-1']
        && $request['sender_id'] === '11111111-1111-4111-8111-111111111111'
        && $request['recipient'] === '0244123456'
        && $request['message'] === 'Your code is 123456');
});

it('keeps only safe parts of the reply: no recipient and no message text', function (): void {
    Http::fake([SMS_URL => Http::response(accepted(), 202)]);

    $receipt = (new PayperlezSmsProvider)->send($this->sms, 'sms-retry-key-1');

    expect($receipt->response)->toBe([
        'status' => 'queued', 'segments' => 1, 'credits' => 1, 'encoding' => 'GSM-7', 'is_test' => true, 'idempotent_replay' => false, 'request_id' => 'req-1',
    ])->and(json_encode($receipt->response))->not->toContain('024')->not->toContain('123456');
});

it('leaves a number outside Ghana in international form', function (): void {
    Http::fake([SMS_URL => Http::response(accepted(), 202)]);

    (new PayperlezSmsProvider)->send(new OutboundSms('01church', '+14155550123', 'Hi', 'note', RecipientType::Member), 'sms-retry-key-1');

    Http::assertSent(fn (Request $request): bool => $request['recipient'] === '+14155550123');
});

it('refuses for good on a bad request, an unapproved sender or too little credit', function (int $status): void {
    Http::fake([SMS_URL => Http::response(['success' => false, 'error' => ['code' => 'SENDER_NOT_APPROVED', 'message' => 'x'], 'meta' => ['request_id' => 'req-9']], $status)]);
    Log::spy();

    expect(fn () => (new PayperlezSmsProvider)->send($this->sms, 'sms-retry-key-1'))
        ->toThrow(SmsRejectedException::class, 'code SENDER_NOT_APPROVED, request req-9');

    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => $context === ['status' => $status, 'error_code' => 'SENDER_NOT_APPROVED', 'request_id' => 'req-9']);
})->with(['bad request' => [400], 'unauthorised' => [401], 'forbidden' => [403], 'conflict' => [409], 'invalid or no credit' => [422]]);

it('lets the job retry on a rate limit or a PayPerlez outage', function (int $status): void {
    Http::fake([SMS_URL => Http::response(['success' => false], $status)]);

    expect(fn () => (new PayperlezSmsProvider)->send($this->sms, 'sms-retry-key-1'))->toThrow(RequestException::class);
})->with(['rate limit' => [429], 'server error' => [500], 'bad gateway' => [502], 'unavailable' => [503]]);

it('lets the job retry when PayPerlez cannot be reached', function (): void {
    Http::fake([SMS_URL => Http::failedConnection()]);

    expect(fn () => (new PayperlezSmsProvider)->send($this->sms, 'sms-retry-key-1'))->toThrow(ConnectionException::class);
});

it('is chosen by SMS_PRIMARY_PROVIDER', function (): void {
    expect(app(SmsProvider::class))->toBeInstanceOf(PayperlezSmsProvider::class);
});

it('refuses to start without the key or the sender id', function (string $setting): void {
    config([$setting => null]);

    expect(fn () => app(SmsProvider::class))->toThrow(SmsProviderUnavailableException::class, 'PAYPERLEZ_API_KEY and PAYPERLEZ_SMS_SENDER_ID');
})->with(['services.payperlez.api_key', 'services.payperlez.sender_id']);
