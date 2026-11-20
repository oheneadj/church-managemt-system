<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the security headers from config/security.php to every response (docs/15, Transport Security).
 */
class SecurityHeaders
{
    /**
     * Sends the request on, then sets the headers on the response. HSTS is only added over HTTPS.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->add(config('security.headers'));

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', config('security.hsts'));
        }

        return $response;
    }
}
