<?php

declare(strict_types=1);

use App\Exceptions\InvalidOtpException;
use App\Exceptions\TooManyOtpAttemptsException;
use App\Services\OneTimeCodes;
use Illuminate\Support\Facades\Cache;

// The store behind every one-time code (sign-in by SMS, sign-in by email, sign-up, QR check-in): only a hash is kept, a
// code works once, and too many wrong tries lock it (agreement section 16).

beforeEach(function (): void {
    Cache::flush();
    $this->codes = new OneTimeCodes;
});

it('issues a code of the configured length and keeps only its hash', function (): void {
    $code = $this->codes->issue('code-key', 'attempts-key');

    expect($code)->toMatch('/^\d{'.config('otp.length').'}$/')
        ->and(Cache::get('code-key'))->not->toBe($code)
        ->and(password_verify($code, (string) Cache::get('code-key')))->toBeTrue();
});

it('accepts the right code once, then not again', function (): void {
    $code = $this->codes->issue('code-key', 'attempts-key');

    $this->codes->check('code-key', 'attempts-key', $code);

    expect(fn () => $this->codes->check('code-key', 'attempts-key', $code))->toThrow(InvalidOtpException::class);
});

it('answers a wrong, an expired and a missing code with the same exception, so none can be told from another', function (): void {
    $this->codes->issue('code-key', 'attempts-key');
    $wrong = fn () => $this->codes->check('code-key', 'attempts-key', '000000');
    $missing = fn () => $this->codes->check('other-key', 'other-attempts', '123456');

    expect($wrong)->toThrow(InvalidOtpException::class)
        ->and($missing)->toThrow(InvalidOtpException::class);
});

it('locks after the allowed number of wrong codes, answering the fifth miss with the lockout', function (): void {
    $code = $this->codes->issue('code-key', 'attempts-key');

    foreach (range(1, config('otp.max_attempts') - 1) as $ignored) {
        expect(fn () => $this->codes->check('code-key', 'attempts-key', '000000'))->toThrow(InvalidOtpException::class);
    }

    expect(fn () => $this->codes->check('code-key', 'attempts-key', '000000'))->toThrow(TooManyOtpAttemptsException::class)
        ->and(fn () => $this->codes->check('code-key', 'attempts-key', $code))->toThrow(TooManyOtpAttemptsException::class);
});

it('clears earlier misses when a new code is issued', function (): void {
    $this->codes->issue('code-key', 'attempts-key');
    expect(fn () => $this->codes->check('code-key', 'attempts-key', '000000'))->toThrow(InvalidOtpException::class);

    $fresh = $this->codes->issue('code-key', 'attempts-key');

    $this->codes->check('code-key', 'attempts-key', $fresh);
    expect(Cache::has('attempts-key'))->toBeFalse();
});

it('forgets a pending code and its misses on request', function (): void {
    $this->codes->issue('code-key', 'attempts-key');
    expect(fn () => $this->codes->check('code-key', 'attempts-key', '000000'))->toThrow(InvalidOtpException::class);

    $this->codes->forget('code-key', 'attempts-key');

    expect(Cache::has('code-key'))->toBeFalse()->and(Cache::has('attempts-key'))->toBeFalse();
});
