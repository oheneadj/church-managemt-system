<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request a correlation id, so one id ties together its log lines, its queued jobs and the
 * support ticket. I accept an id the caller sent only if it looks safe; otherwise I make a new one.
 */
class AssignRequestId
{
    public const HEADER = 'X-Request-ID';

    /**
     * Gives the request an id, adds it to the log context and returns it in the response header.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $sent = (string) $request->header(self::HEADER);
        $id = preg_match('/^[A-Za-z0-9_-]{8,64}$/', $sent) === 1 ? $sent : (string) Str::ulid();

        // Context is added to every log line and carried into jobs dispatched during this request.
        Context::add('request_id', $id);

        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }
}
