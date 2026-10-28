<?php

declare(strict_types=1);

use App\Contracts\SmsProvider;
use App\DTOs\OutboundSms;
use App\DTOs\SmsReceipt;
use App\Enums\MessageStatus;
use App\Enums\RecipientType;
use App\Exceptions\SmsRejectedException;
use App\Jobs\SendSmsJob;
use App\Models\Church;
use App\Models\MessageLog;
use App\Repositories\MessageLogRepositoryInterface;
use App\Services\CircuitBreaker;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

// Agreement section 37: when a provider keeps failing, stop hammering it. A person can also pause sending by hand.

beforeEach(function (): void {
    Cache::flush();
    $this->circuit = app(CircuitBreaker::class);
});

/** Makes `$count` failed calls. */
function failTimes(CircuitBreaker $circuit, int $count): void
{
    foreach (range(1, $count) as $ignored) {
        $circuit->recordFailure('sms');
    }
}

/** A job wired to a fake queue job, so what it does with the message can be checked. */
function smsJobWithQueue(OutboundSms $sms): array
{
    $fake = Mockery::mock(Job::class);

    return [(new SendSmsJob($sms))->setJob($fake), $fake];
}

describe('the circuit', function (): void {
    it('lets calls through when nothing has failed', function (): void {
        expect($this->circuit->allows('sms'))->toBeTrue();
    });

    it('stays closed below the failure threshold', function (): void {
        failTimes($this->circuit, config('resilience.circuit_breaker.failure_threshold') - 1);

        expect($this->circuit->allows('sms'))->toBeTrue();
    });

    it('opens at the threshold and logs it as an error', function (): void {
        Log::spy();

        failTimes($this->circuit, config('resilience.circuit_breaker.failure_threshold'));

        expect($this->circuit->allows('sms'))->toBeFalse();
        Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => $message === 'Circuit opened: calls are being held back' && $context['provider'] === 'sms');
    });

    it('does not count failures that are further apart than the window', function (): void {
        $threshold = config('resilience.circuit_breaker.failure_threshold');
        failTimes($this->circuit, $threshold - 1);
        $this->travel(config('resilience.circuit_breaker.window_seconds') + 1)->seconds();

        failTimes($this->circuit, 1);

        expect($this->circuit->allows('sms'))->toBeTrue();
    });

    it('lets exactly one test call through after the cooldown', function (): void {
        failTimes($this->circuit, config('resilience.circuit_breaker.failure_threshold'));
        $this->travel(config('resilience.circuit_breaker.cooldown_seconds') + 1)->seconds();

        expect($this->circuit->allows('sms'))->toBeTrue()->and($this->circuit->allows('sms'))->toBeFalse();
    });

    it('closes when the test call works', function (): void {
        failTimes($this->circuit, config('resilience.circuit_breaker.failure_threshold'));
        $this->travel(config('resilience.circuit_breaker.cooldown_seconds') + 1)->seconds();
        $this->circuit->allows('sms');

        $this->circuit->recordSuccess('sms');

        expect($this->circuit->allows('sms'))->toBeTrue()->and($this->circuit->allows('sms'))->toBeTrue();
    });

    it('opens again at once when the test call fails', function (): void {
        failTimes($this->circuit, config('resilience.circuit_breaker.failure_threshold'));
        $this->travel(config('resilience.circuit_breaker.cooldown_seconds') + 1)->seconds();
        $this->circuit->allows('sms');

        $this->circuit->recordFailure('sms');

        expect($this->circuit->allows('sms'))->toBeFalse();
    });

    it('keeps one provider\'s circuit apart from another\'s', function (): void {
        failTimes($this->circuit, config('resilience.circuit_breaker.failure_threshold'));

        expect($this->circuit->allows('other-provider'))->toBeTrue();
    });
});

describe('pausing by hand', function (): void {
    it('holds calls back until resumed, however long that takes', function (): void {
        $this->artisan('sms:pause')->expectsOutputToContain('paused')->assertSuccessful();
        $this->travel(7)->days();

        expect($this->circuit->allows('sms'))->toBeFalse()->and($this->circuit->isPaused('sms'))->toBeTrue();

        $this->artisan('sms:resume')->assertSuccessful();

        expect($this->circuit->allows('sms'))->toBeTrue()->and($this->circuit->isPaused('sms'))->toBeFalse();
    });

    it('resume also clears an open circuit', function (): void {
        failTimes($this->circuit, config('resilience.circuit_breaker.failure_threshold'));

        $this->artisan('sms:resume')->assertSuccessful();

        expect($this->circuit->allows('sms'))->toBeTrue();
    });
});

describe('the SMS job', function (): void {
    beforeEach(function (): void {
        $this->church = Church::factory()->create();
        $this->sms = new OutboundSms($this->church->id, '+233244123456', 'Your code is 123456', 'otp', RecipientType::User, null, '[otp redacted]');
        $this->logs = app(MessageLogRepositoryInterface::class);
        $this->provider = Mockery::mock(SmsProvider::class);
    });

    it('holds the message back and puts it on the queue again while the circuit is open', function (): void {
        failTimes($this->circuit, config('resilience.circuit_breaker.failure_threshold'));
        [$job, $queued] = smsJobWithQueue($this->sms);
        $this->provider->shouldNotReceive('send');
        $queued->shouldReceive('release')->once()->with(config('resilience.sms.release_seconds'));

        $job->handle($this->provider, $this->logs, $this->circuit);

        expect(MessageLog::query()->count())->toBe(0);
    });

    it('holds the message back while sending is paused', function (): void {
        $this->circuit->pause('sms');
        [$job, $queued] = smsJobWithQueue($this->sms);
        $this->provider->shouldNotReceive('send');
        $queued->shouldReceive('release')->once();

        $job->handle($this->provider, $this->logs, $this->circuit);
    });

    it('sends the held message once sending is resumed', function (): void {
        $this->circuit->pause('sms');
        $this->circuit->resume('sms');
        [$job] = smsJobWithQueue($this->sms);
        $this->provider->shouldReceive('send')->once()->andReturn(new SmsReceipt);

        $job->handle($this->provider, $this->logs, $this->circuit);

        expect(MessageLog::query()->sole()->status)->toBe(MessageStatus::Sent);
    });

    it('counts a temporary failure against the circuit and lets the job retry', function (): void {
        $this->provider->shouldReceive('send')->andThrow(new RuntimeException('provider down'));

        foreach (range(1, config('resilience.circuit_breaker.failure_threshold')) as $ignored) {
            expect(fn () => (new SendSmsJob($this->sms))->handle($this->provider, $this->logs, $this->circuit))->toThrow(RuntimeException::class);
        }

        expect($this->circuit->allows('sms'))->toBeFalse();
    });

    it('does not count a refusal as the provider being down', function (): void {
        failTimes($this->circuit, config('resilience.circuit_breaker.failure_threshold') - 1);
        [$job, $queued] = smsJobWithQueue($this->sms);
        $queued->shouldReceive('fail')->once();
        $this->provider->shouldReceive('send')->once()->andThrow(new SmsRejectedException('sender not approved'));

        $job->handle($this->provider, $this->logs, $this->circuit);

        // The provider answered, so the failure count started again.
        failTimes($this->circuit, 1);
        expect($this->circuit->allows('sms'))->toBeTrue();
    });

    it('keeps trying for the retry window and fails for good after three real failures', function (): void {
        $job = new SendSmsJob($this->sms);

        expect($job->retryUntil()->getTimestamp())->toBe(now()->addMinutes(config('resilience.sms.retry_minutes'))->getTimestamp())
            ->and($job->maxExceptions)->toBe(config('resilience.sms.max_exceptions'));
    });
});
