<?php

declare(strict_types=1);

use App\Models\Church;
use Illuminate\Support\Facades\Vite;

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

describe('Content-Security-Policy (D-056)', function (): void {
    /** Returns the sources listed for one directive of a policy header. */
    function cspSources(string $policy, string $directive): array
    {
        foreach (explode(';', $policy) as $part) {
            $words = preg_split('/\s+/', trim($part)) ?: [];

            if (($words[0] ?? '') === $directive) {
                return array_slice($words, 1);
            }
        }

        return [];
    }

    it('locks down API responses completely', function (): void {
        $policy = $this->getJson('http://grace.localhost/api/health')->headers->get('Content-Security-Policy');

        expect(cspSources($policy, 'default-src'))->toBe(["'none'"])
            ->and(cspSources($policy, 'frame-ancestors'))->toBe(["'none'"]);
    });

    it('lets the React page load only its own scripts, with a nonce that matches its script tags', function (): void {
        $response = $this->get('http://grace.localhost/members');
        $policy = $response->headers->get('Content-Security-Policy');
        $scripts = cspSources($policy, 'script-src');
        $nonce = collect($scripts)->first(fn (string $source) => str_starts_with($source, "'nonce-"));

        expect($scripts)->toContain("'self'")->not->toContain("'unsafe-inline'")->not->toContain("'unsafe-eval'")
            ->and($nonce)->not->toBeNull()
            ->and(cspSources($policy, 'object-src'))->toBe(["'none'"])
            ->and(cspSources($policy, 'frame-ancestors'))->toBe(["'none'"])
            ->and(cspSources($policy, 'base-uri'))->toBe(["'self'"]);

        $response->assertSee('nonce="'.substr($nonce, 7, -1).'"', false);
    });

    it('uses a new nonce for every request', function (): void {
        $first = $this->get('http://grace.localhost/members')->headers->get('Content-Security-Policy');
        $second = $this->get('http://grace.localhost/members')->headers->get('Content-Security-Policy');

        expect($first)->not->toBe($second);
    });

    it('allows photos from the storage host named by the R2 endpoint', function (): void {
        config(['filesystems.disks.r2.endpoint' => 'https://abc123.r2.cloudflarestorage.com']);

        $images = cspSources($this->get('http://grace.localhost/members')->headers->get('Content-Security-Policy'), 'img-src');

        expect($images)->toContain('https://abc123.r2.cloudflarestorage.com')->toContain('https://*.abc123.r2.cloudflarestorage.com');
    });

    it('allows the dev server\'s scripts, styles, fonts and socket only while it is running', function (): void {
        $hot = tempnam(sys_get_temp_dir(), 'hot');
        file_put_contents($hot, "http://127.0.0.1:5173\n");
        Vite::useHotFile($hot);

        $policy = $this->get('http://grace.localhost/members')->headers->get('Content-Security-Policy');

        expect(cspSources($policy, 'script-src'))->toContain('http://127.0.0.1:5173')
            ->and(cspSources($policy, 'style-src'))->toContain('http://127.0.0.1:5173')
            ->and(cspSources($policy, 'font-src'))->toContain('http://127.0.0.1:5173')->toContain("'self'")
            ->and(cspSources($policy, 'connect-src'))->toContain('http://127.0.0.1:5173')->toContain('ws://127.0.0.1:5173');

        unlink($hot);
    });

    it('allows fonts only from our own address and inline data when no dev server is running', function (): void {
        Vite::useHotFile(sys_get_temp_dir().'/no-such-hot-file');

        $policy = $this->get('http://grace.localhost/members')->headers->get('Content-Security-Policy');

        expect(cspSources($policy, 'font-src'))->toBe(["'self'", 'data:']);
    });

    it('leaves the API documentation page without a policy, because it loads a third-party viewer', function (): void {
        $this->get('http://grace.localhost/docs/api')->assertHeaderMissing('Content-Security-Policy');
    });
});
