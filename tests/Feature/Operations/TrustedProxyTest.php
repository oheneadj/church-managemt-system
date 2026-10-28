<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::get('/api/_proxy-probe', fn () => response()->json([
        'secure' => request()->isSecure(),
        'ip' => request()->ip(),
    ]));
});

it('believes the proxy that Coolify puts in front of the app', function (): void {
    $response = $this->call('GET', '/api/_proxy-probe', server: [
        'REMOTE_ADDR' => '10.0.3.7',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_FOR' => '41.66.0.9',
    ]);

    expect($response->json())->toBe(['secure' => true, 'ip' => '41.66.0.9']);
});

it('also believes a proxy on the default Docker bridge range', function (): void {
    $response = $this->call('GET', '/api/_proxy-probe', server: ['REMOTE_ADDR' => '172.18.0.2', 'HTTP_X_FORWARDED_PROTO' => 'https']);

    expect($response->json('secure'))->toBeTrue();
});

it('ignores forwarded headers from an address that is not a proxy', function (): void {
    $response = $this->call('GET', '/api/_proxy-probe', server: [
        'REMOTE_ADDR' => '203.0.113.9',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_FOR' => '10.1.1.1',
    ]);

    expect($response->json())->toBe(['secure' => false, 'ip' => '203.0.113.9']);
});

it('reads the trusted ranges from TRUSTED_PROXIES', function (): void {
    expect(config('trustedproxy.proxies'))->toBe(['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']);
});
