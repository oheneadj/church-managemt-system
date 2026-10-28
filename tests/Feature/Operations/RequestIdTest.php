<?php

declare(strict_types=1);

use App\Models\Church;
use Illuminate\Support\Facades\Context;

it('adds a request id header to every response', function (): void {
    $id = $this->getJson('http://localhost/api/health')->headers->get('X-Request-ID');

    expect($id)->toBeString()->toHaveLength(26);
});

it('also adds one to a 404 for an unknown church', function (): void {
    $response = $this->getJson('http://nobody.localhost/api/v1/auth/me')->assertNotFound();

    expect($response->headers->get('X-Request-ID'))->not->toBeNull();
});

it('echoes a safe id sent by the caller so a trace can be followed across services', function (): void {
    $this->withHeaders(['X-Request-ID' => 'support-ticket-12345'])
        ->getJson('http://localhost/api/health')
        ->assertHeader('X-Request-ID', 'support-ticket-12345');
});

it('replaces an unsafe id instead of trusting it', function (): void {
    $id = $this->withHeaders(['X-Request-ID' => "bad id\nInjected: yes"])
        ->getJson('http://localhost/api/health')
        ->headers->get('X-Request-ID');

    expect($id)->not->toContain('Injected')->and($id)->toHaveLength(26);
});

it('puts the request id and church id into the logging context', function (): void {
    Church::factory()->create(['slug' => 'grace']);

    $this->getJson('http://grace.localhost/api/v1/auth/me')->assertUnauthorized();

    expect(Context::get('request_id'))->toBeString()
        ->and(Context::get('church_id'))->toBeString()
        ->and(Context::get('service'))->toBe('chms');
});
