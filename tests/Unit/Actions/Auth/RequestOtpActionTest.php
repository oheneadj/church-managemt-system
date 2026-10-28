<?php

declare(strict_types=1);

use App\Actions\Auth\RequestOtpAction;
use App\DTOs\Auth\RequestOtpDTO;
use App\Services\OtpService;
use App\ValueObjects\PhoneNumber;

it('asks the OTP service to send a code to the phone for that church', function (): void {
    $church = unitChurch();
    $phone = PhoneNumber::tryFrom('0244123456');
    $otp = Mockery::mock(OtpService::class);
    $otp->shouldReceive('request')->once()->with($church, $phone);

    (new RequestOtpAction($otp))->execute(new RequestOtpDTO($church, $phone));
});
