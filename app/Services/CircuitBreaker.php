<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Stops hammering a provider that is down (agreement section 37). It counts failures; past the threshold the
 * circuit opens and calls are held back for a cooldown; then one test call is let through, and its result closes
 * or reopens the circuit. A person can also pause a provider by hand, for example during an incident.
 * The state lives in the cache, so every worker sees the same circuit.
 */
class CircuitBreaker
{
    /** How long the "has opened" marker is kept. It stays after the cooldown so the next failure reopens at once. */
    private const MARKER_SECONDS = 86400;

    /**
     * Whether a call to the provider may go ahead right now. After the cooldown exactly one caller gets
     * a yes (the test call); everyone else keeps waiting for its result.
     */
    public function allows(string $name): bool
    {
        if (Cache::has($this->key($name, 'paused'))) {
            return false;
        }

        $openUntil = Cache::get($this->key($name, 'open-until'));

        if ($openUntil === null) {
            return true;
        }

        if (now()->getTimestamp() < (int) $openUntil) {
            return false;
        }

        return Cache::add($this->key($name, 'probe'), true, config('resilience.circuit_breaker.probe_seconds'));
    }

    /**
     * Records a failed call. The circuit opens at the threshold, or at once when the failed call was the test call.
     */
    public function recordFailure(string $name): void
    {
        $testCall = Cache::has($this->key($name, 'open-until'));
        $failures = $this->countFailure($name);

        if ($testCall || $failures >= config('resilience.circuit_breaker.failure_threshold')) {
            $this->open($name);
        }
    }

    /**
     * Records a call that worked: the circuit closes and the failure count starts again.
     */
    public function recordSuccess(string $name): void
    {
        Cache::forget($this->key($name, 'open-until'));
        Cache::forget($this->key($name, 'probe'));
        Cache::forget($this->key($name, 'failures'));
    }

    /**
     * Holds back every call to the provider until someone resumes it (the `sms:pause` command).
     */
    public function pause(string $name): void
    {
        Cache::forever($this->key($name, 'paused'), true);
    }

    /**
     * Lets calls through again and clears the failure history.
     */
    public function resume(string $name): void
    {
        Cache::forget($this->key($name, 'paused'));
        $this->recordSuccess($name);
    }

    /**
     * Whether a person paused the provider.
     */
    public function isPaused(string $name): bool
    {
        return Cache::has($this->key($name, 'paused'));
    }

    /**
     * Adds one failure to the count for the window and returns the new count.
     */
    private function countFailure(string $name): int
    {
        $key = $this->key($name, 'failures');
        Cache::add($key, 0, config('resilience.circuit_breaker.window_seconds'));

        return (int) Cache::increment($key);
    }

    /**
     * Opens the circuit for the cooldown and starts the failure count again.
     */
    private function open(string $name): void
    {
        $until = now()->addSeconds(config('resilience.circuit_breaker.cooldown_seconds'))->getTimestamp();

        Cache::put($this->key($name, 'open-until'), $until, self::MARKER_SECONDS);
        Cache::forget($this->key($name, 'probe'));
        Cache::forget($this->key($name, 'failures'));

        Log::error('Circuit opened: calls are being held back', ['provider' => $name, 'retry_after' => $until]);
    }

    /**
     * The cache key of one piece of a provider's circuit state.
     */
    private function key(string $name, string $part): string
    {
        return "circuit:{$name}:{$part}";
    }
}
