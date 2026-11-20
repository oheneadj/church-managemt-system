<?php

declare(strict_types=1);

use App\Exceptions\ChurchSlugTakenException;
use App\Exceptions\FamilyHeadAlreadyTakenException;
use App\Exceptions\InvalidOtpException;
use App\Exceptions\MemberNumberPrefixMissingException;
use App\Exceptions\SmsRejectedException;
use App\Exceptions\TooManyOtpAttemptsException;
use Illuminate\Contracts\Debug\ExceptionHandler;

// A mistake the user made (wrong code, taken address) is answered with a 4xx but is not an error of ours, so it
// must not fill the log or the error monitor. A real fault (the SMS provider refusing us) must still be reported.

it('does not report mistakes the user made', function (Throwable $exception): void {
    expect(app(ExceptionHandler::class)->shouldReport($exception))->toBeFalse();
})->with([
    'wrong code' => fn () => new InvalidOtpException,
    'locked out' => fn () => new TooManyOtpAttemptsException,
    'taken address' => fn () => new ChurchSlugTakenException(['grace-gh']),
    'family head taken' => fn () => new FamilyHeadAlreadyTakenException,
    'no member prefix' => fn () => new MemberNumberPrefixMissingException,
]);

it('still reports real faults', function (Throwable $exception): void {
    expect(app(ExceptionHandler::class)->shouldReport($exception))->toBeTrue();
})->with([
    'sms refused' => fn () => new SmsRejectedException('refused'),
    'any other error' => fn () => new RuntimeException('boom'),
]);
