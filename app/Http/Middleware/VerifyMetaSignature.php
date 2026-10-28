<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a webhook through only when Meta signed it: the X-Hub-Signature-256 header must be the HMAC-SHA256 of the raw
 * body made with our app secret (docs/15, webhooks). It runs before anything reads the payload. Without a secret in
 * config nothing passes, so a missing setting can never leave the door open.
 */
class VerifyMetaSignature
{
    /**
     * Answers 403 unless the signature matches the body.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.meta.app_secret');
        $given = (string) $request->headers->get(config('whatsapp.signature_header'), '');
        $expected = config('whatsapp.signature_prefix').hash_hmac('sha256', $request->getContent(), $secret);

        if ($secret === '' || ! hash_equals($expected, $given)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
