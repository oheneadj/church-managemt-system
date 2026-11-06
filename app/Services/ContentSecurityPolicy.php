<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;

/**
 * Builds the Content-Security-Policy header text from `config/security.php` (D-056).
 * One policy for the React page, a locked-down one for JSON, and the Vite dev server added only while it runs.
 */
class ContentSecurityPolicy
{
    /**
     * Whether this request is left without a policy: the API docs page loads a third-party viewer (staff only, D-035).
     */
    public function isExempt(Request $request): bool
    {
        return $request->is(...config('security.csp.exempt_paths'));
    }

    /**
     * The policy for responses that are not pages (JSON, files): nothing may load and nothing may frame it.
     */
    public function forApi(): string
    {
        return $this->format(config('security.csp.api'));
    }

    /**
     * The policy for the React page: scripts from us carrying the request's nonce, our own images and the storage host for photos.
     */
    public function forPage(string $nonce): string
    {
        $directives = config('security.csp.page');

        $directives['script-src'][] = "'nonce-{$nonce}'";
        $directives['img-src'] = [...$directives['img-src'], ...$this->imageHosts()];

        if (Vite::isRunningHot()) {
            $directives = $this->withDevServer($directives, $this->devServerOrigin());
        }

        return $this->format($directives);
    }

    /**
     * The storage host (and its sub-hosts) that signed photo links point to, taken from the R2 endpoint.
     *
     * @return list<string>
     */
    private function imageHosts(): array
    {
        $host = parse_url((string) config('filesystems.disks.r2.endpoint'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? ["https://{$host}", "https://*.{$host}"] : [];
    }

    /**
     * The address Vite's dev server runs on, from the "hot" file the Laravel plugin writes while it runs.
     */
    private function devServerOrigin(): string
    {
        return rtrim(trim((string) file_get_contents(Vite::hotFile())), '/');
    }

    /**
     * Local development only: lets the page load scripts and styles from the dev server and talk to it over its socket.
     *
     * @param  array<string, list<string>>  $directives
     * @return array<string, list<string>>
     */
    private function withDevServer(array $directives, string $origin): array
    {
        $socket = preg_replace('/^http/', 'ws', $origin);

        $directives['script-src'][] = $origin;
        $directives['style-src'][] = $origin;
        $directives['connect-src'] = [...$directives['connect-src'], $origin, (string) $socket];

        return $directives;
    }

    /**
     * Turns a map of directives into the header text.
     *
     * @param  array<string, list<string>>  $directives
     */
    private function format(array $directives): string
    {
        return collect($directives)
            ->map(fn (array $sources, string $name): string => trim($name.' '.implode(' ', $sources)))
            ->implode('; ');
    }
}
