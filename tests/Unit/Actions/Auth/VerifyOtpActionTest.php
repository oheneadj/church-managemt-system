<?php

declare(strict_types=1);

use App\Actions\Auth\VerifyOtpAction;
use App\DTOs\Auth\VerifyOtpDTO;
use App\Exceptions\InvalidOtpException;
use App\Models\PersonalAccessToken;
use App\Services\AuthSessionService;
use App\Services\OtpService;
use App\ValueObjects\PhoneNumber;
use Illuminate\Contracts\Session\Session;
use Laravel\Sanctum\NewAccessToken;

beforeEach(function (): void {
    $this->church = unitChurch();
    $this->phone = PhoneNumber::tryFrom('0244123456');
    $this->user = unitUser();
    $this->otp = Mockery::mock(OtpService::class);
    $this->sessions = Mockery::mock(AuthSessionService::class);
    $this->action = new VerifyOtpAction($this->otp, $this->sessions);
});

it('signs the browser app in by cookie and returns no token', function (): void {
    $session = Mockery::mock(Session::class);
    $this->otp->shouldReceive('verify')->once()->with($this->church, $this->phone, '382910')->andReturn($this->user);
    $this->sessions->shouldReceive('startBrowserSession')->once()->with($this->user, $session);
    $this->sessions->shouldReceive('startTokenSession')->never();

    $result = $this->action->execute(new VerifyOtpDTO($this->church, $this->phone, '382910', $session));

    expect($result->user)->toBe($this->user)->and($result->church)->toBe($this->church)->and($result->token)->toBeNull();
});

it('gives any other client a bearer token', function (): void {
    $this->otp->shouldReceive('verify')->once()->andReturn($this->user);
    $this->sessions->shouldReceive('startTokenSession')->once()->with($this->user)->andReturn(new NewAccessToken(new PersonalAccessToken, '1|plain-text-token'));
    $this->sessions->shouldReceive('startBrowserSession')->never();

    $result = $this->action->execute(new VerifyOtpDTO($this->church, $this->phone, '382910'));

    expect($result->token)->toBe('1|plain-text-token');
});

it('starts no session when the code is wrong', function (): void {
    $this->otp->shouldReceive('verify')->andThrow(new InvalidOtpException);
    $this->sessions->shouldReceive('startBrowserSession')->never();
    $this->sessions->shouldReceive('startTokenSession')->never();

    expect(fn () => $this->action->execute(new VerifyOtpDTO($this->church, $this->phone, '000000')))->toThrow(InvalidOtpException::class);
});
