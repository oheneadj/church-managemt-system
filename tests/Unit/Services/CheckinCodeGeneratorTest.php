<?php

declare(strict_types=1);

use App\Repositories\ServiceRepositoryInterface;
use App\Services\CheckinCodeGenerator;

beforeEach(function (): void {
    $this->services = Mockery::mock(ServiceRepositoryInterface::class);
    $this->generator = new CheckinCodeGenerator($this->services);
});

it('makes a code of the configured length from the allowed letters only', function (): void {
    $this->services->shouldReceive('checkinCodeExists')->andReturnFalse();

    foreach (range(1, 200) as $ignored) {
        expect($this->generator->next())->toMatch('/^['.config('attendance.checkin_code_alphabet').']{'.config('attendance.checkin_code_length').'}$/');
    }
});

it('never uses letters that look alike', function (): void {
    expect(config('attendance.checkin_code_alphabet'))->not->toContain('0')->not->toContain('O')->not->toContain('1')->not->toContain('I');
});

it('skips a code that is already used', function (): void {
    $this->services->shouldReceive('checkinCodeExists')->andReturn(true, true, false);

    expect($this->generator->next())->toBeString();
});

it('fails clearly when no free code can be found', function (): void {
    config(['attendance.checkin_code_attempts' => 5]);
    $this->services->shouldReceive('checkinCodeExists')->times(5)->andReturnTrue();

    expect(fn () => $this->generator->next())->toThrow(RuntimeException::class, 'free check-in code');
});
