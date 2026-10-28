<?php

declare(strict_types=1);

use App\Actions\Onboarding\RegisterChurchAction;
use App\DTOs\Onboarding\RegisterChurchDTO;
use App\Exceptions\ChurchSlugTakenException;
use App\Exceptions\InvalidOtpException;
use App\Repositories\ChurchRepositoryInterface;
use App\Repositories\UserRepositoryInterface;
use App\Services\ChurchSlugGenerator;
use App\Services\OnboardingOtpService;
use App\ValueObjects\PhoneNumber;

// The success path touches roles and the database, so the feature tests cover it. These cover the order of the
// checks and that nothing is saved when one fails.

beforeEach(function (): void {
    withoutDatabaseTransactions();
    $this->churches = Mockery::mock(ChurchRepositoryInterface::class);
    $this->users = Mockery::mock(UserRepositoryInterface::class);
    $this->slugs = Mockery::mock(ChurchSlugGenerator::class);
    $this->otp = Mockery::mock(OnboardingOtpService::class);
    $this->action = new RegisterChurchAction($this->churches, $this->users, $this->slugs, $this->otp);
    $this->dto = fn (?string $slug = 'grace') => new RegisterChurchDTO(
        churchName: 'Grace Assembly',
        slug: $slug,
        denomination: null,
        city: null,
        memberNumberPrefix: 'GRC',
        adminName: 'Kofi Mensah',
        adminPhone: PhoneNumber::tryFrom('+233244300001'),
        otp: '123456',
    );
});

it('refuses a taken address with suggestions before it uses up the code', function (): void {
    $this->slugs->shouldReceive('isAvailable')->once()->with('grace')->andReturnFalse();
    $this->slugs->shouldReceive('suggestionsFor')->once()->with('grace')->andReturn(['grace-gh']);
    $this->otp->shouldNotReceive('verify');
    $this->churches->shouldNotReceive('create');

    try {
        $this->action->execute(($this->dto)());
        $this->fail('Expected the slug to be refused.');
    } catch (ChurchSlugTakenException $exception) {
        expect($exception->suggestions)->toBe(['grace-gh']);
    }
});

it('builds the address from the church name when none is typed', function (): void {
    $this->slugs->shouldReceive('fromName')->once()->with('Grace Assembly')->andReturn('grace-assembly');
    $this->slugs->shouldNotReceive('isAvailable');
    $this->otp->shouldReceive('verify')->once()->andThrow(new InvalidOtpException);

    expect(fn () => $this->action->execute(($this->dto)(null)))->toThrow(InvalidOtpException::class);
});

it('creates nothing when the code is wrong', function (): void {
    $this->slugs->shouldReceive('isAvailable')->andReturnTrue();
    $this->otp->shouldReceive('verify')->once()->with(Mockery::type(PhoneNumber::class), '123456')->andThrow(new InvalidOtpException);
    $this->churches->shouldNotReceive('create');
    $this->users->shouldNotReceive('create');

    expect(fn () => $this->action->execute(($this->dto)()))->toThrow(InvalidOtpException::class);
});
