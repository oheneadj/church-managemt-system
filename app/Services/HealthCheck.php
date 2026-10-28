<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Runs the readiness checks behind GET /api/health (docs/16 and docs/17).
 * The result says only ok or error per check: the reason goes to the log, never to the caller.
 */
class HealthCheck
{
    /** The cache key and lifetime (seconds) of the value the cache check writes and reads back. */
    private const CACHE_PROBE_KEY = 'health-check';

    private const CACHE_PROBE_SECONDS = 10;

    /** The queue that is checked: the one OTP texts use, which must never be stuck. */
    private const QUEUE_PROBE = 'critical';

    /**
     * Takes the named checks to run.
     *
     * @param  array<string, Closure(): mixed>  $checks  Each check throws when its dependency is unhealthy.
     */
    public function __construct(private readonly array $checks) {}

    /**
     * The checks used in the running app.
     */
    public static function standard(): self
    {
        return new self([
            'database' => fn () => DB::connection()->getPdo(),
            'cache' => function (): void {
                Cache::put(self::CACHE_PROBE_KEY, 'ok', self::CACHE_PROBE_SECONDS);

                if (Cache::get(self::CACHE_PROBE_KEY) !== 'ok') {
                    throw new RuntimeException('Cache did not return the value just written.');
                }
            },
            'queue' => fn () => Queue::size(self::QUEUE_PROBE),
            'storage' => fn () => Storage::put('health-check.txt', 'ok'),
            'disk' => function (): void {
                $free = disk_free_space(storage_path());
                $total = disk_total_space(storage_path());

                if ($free === false || $total === false || ($free / $total) * 100 < (float) config('health.min_free_disk_percent')) {
                    throw new RuntimeException('Free disk space is below the configured minimum.');
                }
            },
        ]);
    }

    /**
     * Runs every check and reports ok or error for each, logging the reason for any failure.
     *
     * @return array<string, 'ok'|'error'>
     */
    public function run(): array
    {
        $results = [];

        foreach ($this->checks as $name => $check) {
            try {
                $check();
                $results[$name] = 'ok';
            } catch (Throwable $e) {
                $results[$name] = 'error';
                Log::error('Health check failed', ['check' => $name, 'exception' => $e::class, 'reason' => $e->getMessage()]);
            }
        }

        return $results;
    }
}
