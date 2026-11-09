<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a webhook through only when PayPerlez signed it (docs/14, section 7; docs/15, webhooks). The header is
 * `t=<unix seconds>,v1=<hex>`, where v1 is the HMAC-SHA256 of "t.body" made with our webhook secret. A signed time more than
 * the allowed number of seconds from ours is refused, so an old capture cannot be replayed. It runs before anything reads the
 * payload. Without a secret in config nothing passes, so a missing setting can never leave the door open.
 */
class VerifyPayperlezSignature
{
    /**
     * Answers 401 unless the signature is fresh and matches the raw body.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.payperlez.webhook_secret');
        parse_str(str_replace(',', '&', (string) $request->headers->get('X-Payperlez-Signature', '')), $parts);
        $timestamp = is_string($parts['t'] ?? null) ? $parts['t'] : '';
        $provided = is_string($parts['v1'] ?? null) ? $parts['v1'] : '';

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        $fresh = ctype_digit($timestamp) && abs(time() - (int) $timestamp) <= (int) config('services.payperlez.webhook_tolerance_seconds');

        if ($secret === '' || ! $fresh || ! hash_equals($expected, $provided)) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
