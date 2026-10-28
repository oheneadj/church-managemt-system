<?php

declare(strict_types=1);

use App\Models\Church;
use App\Models\Service;
use App\Services\QrTokenService;
use Illuminate\Support\Facades\Cache;

// A QR code is a random string kept in the cache. The church is part of the key, so a church's code never opens another
// church's service even before the service lookup (a second lock, agreement section 34: cache keys are tenant-namespaced).

beforeEach(function (): void {
    Cache::flush();
    $this->tokens = app(QrTokenService::class);
    $this->church = Church::factory()->create();
    $this->service = Service::factory()->create(['church_id' => $this->church->id]);
});

it('finds the service a code was made for', function (): void {
    $code = $this->tokens->issue($this->church, $this->service);

    expect($this->tokens->serviceIdFor($this->church, $code->token))->toBe($this->service->id);
});

it('does not find it for another church', function (): void {
    $code = $this->tokens->issue($this->church, $this->service);

    expect($this->tokens->serviceIdFor(Church::factory()->create(), $code->token))->toBeNull();
});

it('does not find a code that was never made', function (): void {
    expect($this->tokens->serviceIdFor($this->church, str_repeat('z', 32)))->toBeNull();
});

it('stops finding a code after it expires', function (): void {
    $code = $this->tokens->issue($this->church, $this->service);

    $this->travel(config('attendance.qr_ttl_seconds') + 1)->seconds();

    expect($this->tokens->serviceIdFor($this->church, $code->token))->toBeNull();
});

it('reports when the code stops working', function (): void {
    $this->travelTo('2026-10-25 09:00:00');

    $code = $this->tokens->issue($this->church, $this->service);

    expect($code->expiresAt->toIso8601String())->toBe('2026-10-25T09:02:00+00:00');
});

it('makes a different long random code every time', function (): void {
    $codes = collect(range(1, 20))->map(fn () => $this->tokens->issue($this->church, $this->service)->token);

    expect($codes->unique())->toHaveCount(20)->and($codes->every(fn (string $token) => strlen($token) === config('attendance.qr_token_length')))->toBeTrue();
});
