<?php

declare(strict_types=1);

use App\Actions\Auth\ResetOtpLockoutAction;
use App\Actions\Auth\ShowCurrentStaffAction;
use App\DTOs\Auth\ResetOtpLockoutDTO;
use App\DTOs\Auth\ShowCurrentStaffDTO;
use App\Services\OtpService;
use App\ValueObjects\PhoneNumber;

it('returns the staff member together with their church', function (): void {
    $user = unitUser();
    $church = unitChurch();

    $result = (new ShowCurrentStaffAction)->execute(new ShowCurrentStaffDTO($user, $church));

    expect($result->user)->toBe($user)->and($result->church)->toBe($church);
});

it('clears the lockout for that church and phone', function (): void {
    $church = unitChurch();
    $phone = PhoneNumber::tryFrom('0244123456');
    $otp = Mockery::mock(OtpService::class);
    $otp->shouldReceive('resetLockout')->once()->with($church, $phone);

    (new ResetOtpLockoutAction($otp))->execute(new ResetOtpLockoutDTO($church, $phone));
});
