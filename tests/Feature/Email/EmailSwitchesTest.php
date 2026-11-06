<?php

declare(strict_types=1);

use App\Services\CircuitBreaker;
use Illuminate\Support\Facades\Cache;

// The incident switches (D-043): email can be held back by hand without losing any message, like sms:pause.

beforeEach(fn () => Cache::flush());

it('pauses email, so queued emails wait', function (): void {
    $this->artisan('email:pause')->expectsOutputToContain('Email is paused')->assertSuccessful();

    expect(app(CircuitBreaker::class)->isPaused('email'))->toBeTrue()
        ->and(app(CircuitBreaker::class)->allows('email'))->toBeFalse();
});

it('resumes email and clears an open circuit', function (): void {
    $circuit = app(CircuitBreaker::class);
    $circuit->pause('email');
    foreach (range(1, config('resilience.circuit_breaker.failure_threshold')) as $ignored) {
        $circuit->recordFailure('email');
    }

    $this->artisan('email:resume')->expectsOutputToContain('Email is flowing again')->assertSuccessful();

    expect($circuit->isPaused('email'))->toBeFalse()->and($circuit->allows('email'))->toBeTrue();
});

it('does not touch the text message switch', function (): void {
    $circuit = app(CircuitBreaker::class);

    $this->artisan('email:pause')->assertSuccessful();

    expect($circuit->isPaused('sms'))->toBeFalse();
});
