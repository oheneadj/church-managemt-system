<?php

declare(strict_types=1);

use App\Models\Church;
use App\Models\User;

beforeEach(function (): void {
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->user = User::factory()->for($this->church)->create();
});

it('rejects me without a token', function (): void {
    $this->getJson('http://grace.localhost/api/v1/auth/me')->assertUnauthorized();
});

it('rejects logout without a token', function (): void {
    $this->postJson('http://grace.localhost/api/v1/auth/logout')->assertUnauthorized();
});

it('revokes the token on logout', function (): void {
    $token = $this->user->createToken('staff')->plainTextToken;

    $this->withToken($token)->postJson('http://grace.localhost/api/v1/auth/logout')->assertNoContent();

    expect($this->user->tokens()->count())->toBe(0);
});

it('does not return another church\'s user from me', function (): void {
    $other = Church::factory()->create(['slug' => 'other']);
    $outsider = User::factory()->for($other)->create();
    $token = $outsider->createToken('staff')->plainTextToken;

    $this->withToken($token)->getJson('http://grace.localhost/api/v1/auth/me')->assertUnauthorized();
});

it('rejects an expired token', function (): void {
    $token = $this->user->createToken('staff', ['*'], now()->subMinute())->plainTextToken;

    $this->withToken($token)->getJson('http://grace.localhost/api/v1/auth/me')->assertUnauthorized();
});
