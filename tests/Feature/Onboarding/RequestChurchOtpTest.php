<?php

declare(strict_types=1);

use App\Jobs\SendSmsJob;
use App\Models\Church;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

// Starting a church (docs/11 story 2.1, QA-03). The sign-up code is texted before any church exists, so
// these routes live on the platform host and never on a church's subdomain.

const PLATFORM = 'http://localhost/api/v1/onboarding/otp';

beforeEach(function (): void {
    RateLimiter::clear('onboarding');
});

it('queues a sign-up code on the critical queue with no church attached', function (): void {
    Queue::fake();

    $this->postJson(PLATFORM, ['admin_phone' => '+233244300001'])
        ->assertOk()
        ->assertJson(['message' => 'OTP sent', 'expires_in' => 600]);

    Queue::assertPushedOn('critical', SendSmsJob::class, fn (SendSmsJob $job): bool => $job->sms->churchId === null
        && $job->sms->phone === '+233244300001'
        && $job->sms->messageType === 'onboarding_otp'
        && $job->sms->logContent === '[otp redacted]');
});

it('accepts a local Ghana number and normalises it', function (): void {
    Queue::fake();

    $this->postJson(PLATFORM, ['admin_phone' => '024 430 0001'])->assertOk();

    Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->sms->phone === '+233244300001');
});

it('stores only a hash of the code, never the code itself', function (): void {
    Queue::fake();
    $this->postJson(PLATFORM, ['admin_phone' => '+233244300001'])->assertOk();

    $code = null;
    Queue::assertPushed(SendSmsJob::class, function (SendSmsJob $job) use (&$code): bool {
        preg_match('/\b(\d{6})\b/', $job->sms->message, $matches);
        $code = $matches[1];

        return true;
    });

    $stored = Cache::get('church:onboarding:otp:'.hash('sha256', '+233244300001'));

    expect($stored)->toBeString()->not->toBe($code);
});

it('rejects a missing or malformed phone number', function (string $phone): void {
    Queue::fake();

    $this->postJson(PLATFORM, ['admin_phone' => $phone])->assertUnprocessable()->assertJsonValidationErrors('admin_phone');

    Queue::assertNothingPushed();
})->with(['empty' => [''], 'letters' => ['abc'], 'too short' => ['0244']]);

it('allows one code per phone number per minute', function (): void {
    Queue::fake();

    $this->postJson(PLATFORM, ['admin_phone' => '+233244300001'])->assertOk();
    $this->postJson(PLATFORM, ['admin_phone' => '+233244300001'])->assertTooManyRequests();

    Queue::assertPushedTimes(SendSmsJob::class, 1);
});

it('limits how many codes one IP address can ask for in an hour', function (): void {
    Queue::fake();

    foreach (range(1, config('limits.onboarding_otp_per_hour_per_ip')) as $number) {
        $this->postJson(PLATFORM, ['admin_phone' => '+2332443000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT)])->assertOk();
    }

    $this->postJson(PLATFORM, ['admin_phone' => '+233244399999'])->assertTooManyRequests();
});

it('is not available on a church subdomain', function (): void {
    Church::factory()->create(['slug' => 'grace']);

    $this->postJson('http://grace.localhost/api/v1/onboarding/otp', ['admin_phone' => '+233244300001'])->assertNotFound();
});
