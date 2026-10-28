<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Circuit breakers on outside providers (agreement section 37)
    |--------------------------------------------------------------------------
    | When a provider keeps failing, retrying every message at once only makes recovery worse. After
    | `failure_threshold` failures within `window_seconds` the circuit opens: calls are held back for
    | `cooldown_seconds`, then one test call is let through. If it works the circuit closes; if not it opens again.
    */
    'circuit_breaker' => [
        'failure_threshold' => 5,
        'window_seconds' => 120,
        'cooldown_seconds' => 60,
        // How long the single test call may take before another one is allowed.
        'probe_seconds' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Text messages held back by an open circuit
    |--------------------------------------------------------------------------
    | A held message is put back on the queue after `release_seconds`, and is given up on only after
    | `retry_minutes` in total (a sign-in code is useless after its 10 minutes, but a receipt is not).
    | `max_exceptions` real failures of one message still fail it for good.
    */
    'sms' => [
        'release_seconds' => 30,
        'retry_minutes' => 30,
        'max_exceptions' => 3,
    ],

];
