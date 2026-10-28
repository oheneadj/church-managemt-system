<?php

declare(strict_types=1);

use App\Models\Church;

beforeEach(fn () => Church::factory()->create(['slug' => 'grace']));

it('adds the security headers to API responses', function (): void {
    $this->getJson('http://grace.localhost/api/health')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-XSS-Protection', '0')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('adds them to the React app page and to error responses too', function (): void {
    $this->get('http://grace.localhost/members')->assertHeader('X-Frame-Options', 'DENY');
    $this->getJson('http://grace.localhost/api/v1/members')->assertUnauthorized()->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('sends HSTS over HTTPS only', function (): void {
    $this->getJson('https://grace.localhost/api/health')->assertHeader('Strict-Transport-Security', config('security.hsts'));
    $this->getJson('http://grace.localhost/api/health')->assertHeaderMissing('Strict-Transport-Security');
});
