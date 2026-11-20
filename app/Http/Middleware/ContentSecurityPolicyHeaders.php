<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ContentSecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the Content-Security-Policy header to every response (Laravel addendum section 8, D-056).
 * I tell Vite to use a nonce before the page is rendered, so the script tags it prints carry it.
 */
class ContentSecurityPolicyHeaders
{
    public function __construct(private readonly ContentSecurityPolicy $policy) {}

    /**
     * Sends the request on, then sets the policy that fits the response: the page policy for HTML, the locked-down one for everything else.
     */
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        if ($this->policy->isExempt($request)) {
            return $response;
        }

        $isPage = str_starts_with((string) $response->headers->get('Content-Type'), 'text/html');

        $response->headers->set('Content-Security-Policy', $isPage ? $this->policy->forPage((string) Vite::cspNonce()) : $this->policy->forApi());

        return $response;
    }
}
