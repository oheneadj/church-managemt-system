<?php

declare(strict_types=1);

use App\Services\HealthCheck;

it('reports every dependency as ok when all is well', function (): void {
    $this->getJson('http://localhost/api/health')
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
            'checks' => ['database' => 'ok', 'cache' => 'ok', 'queue' => 'ok', 'storage' => 'ok', 'disk' => 'ok'],
        ])
        ->assertJsonStructure(['timestamp']);
});

it('needs no sign-in and no church, so monitors can call it on the bare host', function (): void {
    $this->getJson('http://api.example.test/api/health')->assertOk();
});

it('returns 503 and names the failing check without leaking why', function (): void {
    $this->app->bind(HealthCheck::class, fn () => new HealthCheck([
        'database' => fn () => null,
        'storage' => fn () => throw new RuntimeException('secret-host.internal refused the connection'),
    ]));

    $response = $this->getJson('http://localhost/api/health')
        ->assertStatus(503)
        ->assertJson(['status' => 'degraded', 'checks' => ['database' => 'ok', 'storage' => 'error']]);

    expect($response->getContent())->not->toContain('secret-host');
});

it('fails the disk check when free space is below the minimum', function (): void {
    config(['health.min_free_disk_percent' => 101]);

    $this->getJson('http://localhost/api/health')
        ->assertStatus(503)
        ->assertJsonPath('checks.disk', 'error');
});

it('logs the reason for a failure server-side', function (): void {
    Log::spy();
    $this->app->bind(HealthCheck::class, fn () => new HealthCheck(['cache' => fn () => throw new RuntimeException('boom')]));

    $this->getJson('http://localhost/api/health')->assertStatus(503);

    Log::shouldHaveReceived('error')->with('Health check failed', Mockery::on(fn (array $context): bool => $context['check'] === 'cache' && $context['reason'] === 'boom'));
});

it('is not rate limited into uselessness by a monitor polling every minute', function (): void {
    foreach (range(1, 5) as $i) {
        $this->getJson('http://localhost/api/health')->assertOk();
    }
});
