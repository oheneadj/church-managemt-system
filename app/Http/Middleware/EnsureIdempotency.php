<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a write safe to repeat (agreement section 11, layer 1). The caller sends an `Idempotency-Key` header, one
 * new value for each thing they mean to do. The first successful answer is kept for a while; the same key sent
 * again, because of a double click or a retry after a timeout, gets that answer back and does nothing a second time.
 * The same key with a different request is refused, and two requests with one key at the same moment take turns
 * (a request that waits too long for the first is told to try again: 409).
 * Failed requests are not kept, so a corrected request can use the same key.
 */
class EnsureIdempotency
{
    /** The header the caller sends the key in. */
    public const HEADER = 'Idempotency-Key';

    /** Added to a replayed answer, so a caller can tell it is the first answer again. */
    public const REPLAY_HEADER = 'Idempotent-Replayed';

    /** What a key may look like: letters, digits, dashes and underscores, so it is safe in a cache key. */
    private const KEY_PATTERN = '/^[A-Za-z0-9_-]{8,100}$/';

    /**
     * Runs the request once per key, or replays the answer to the first one.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header(self::HEADER);

        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            return response()->json(['message' => 'An Idempotency-Key header of 8 to 100 letters, digits, dashes or underscores is required.'], Response::HTTP_BAD_REQUEST);
        }

        $cacheKey = $this->cacheKey($request, $key);
        $fingerprint = hash('sha256', $request->method().$request->path().$request->getContent());

        // The lock makes a second request with the same key wait for the first, instead of racing it.
        try {
            return Cache::lock($cacheKey.':lock', config('limits.idempotency_lock_seconds'))
                ->block(config('limits.idempotency_lock_seconds'), fn (): Response => $this->handleLocked($request, $next, $cacheKey, $fingerprint));
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'A request with this Idempotency-Key is still being processed. Try again in a moment.'], Response::HTTP_CONFLICT);
        }
    }

    /**
     * Replays the kept answer, refuses a different request, or runs this one and keeps its answer.
     */
    private function handleLocked(Request $request, Closure $next, string $cacheKey, string $fingerprint): Response
    {
        $kept = Cache::get($cacheKey);

        if (is_array($kept)) {
            return $kept['fingerprint'] === $fingerprint
                ? $this->replay($kept)
                : response()->json(['message' => 'This Idempotency-Key was already used for a different request.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $response = $next($request);

        if ($response->isSuccessful()) {
            Cache::put($cacheKey, [
                'fingerprint' => $fingerprint,
                'status' => $response->getStatusCode(),
                'content' => $response->getContent(),
            ], now()->addMinutes(config('limits.idempotency_ttl_minutes')));
        }

        return $response;
    }

    /**
     * The first answer again, marked as a replay.
     *
     * @param  array<string, mixed>  $kept
     */
    private function replay(array $kept): JsonResponse
    {
        return response()->json(json_decode((string) $kept['content'], true), $kept['status'], [self::REPLAY_HEADER => 'true']);
    }

    /**
     * The cache key: the church, the person and the route keep one caller's keys apart from another's.
     */
    private function cacheKey(Request $request, string $key): string
    {
        return 'idempotency:'.app('current.church')->getKey().':'.$request->user()?->getAuthIdentifier().':'.$request->route()?->getName().':'.$key;
    }
}
