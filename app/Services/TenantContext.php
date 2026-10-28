<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Church;
use Closure;

/**
 * Runs some work as one church, for code that has no request to tell it which church it is (a webhook, a queued job).
 * The church that was current before is put back afterwards, so a worker that handles many churches never leaks one's
 * context into the next job.
 */
class TenantContext
{
    /**
     * Makes this church the current one while the callback runs, then restores the previous state.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function run(Church $church, Closure $callback): mixed
    {
        $previous = $this->current();
        app()->instance('current.church', $church);

        try {
            return $callback();
        } finally {
            if ($previous === null) {
                app()->forgetInstance('current.church');
            } else {
                app()->instance('current.church', $previous);
            }
        }
    }

    /**
     * The church that is current right now, or null when none is.
     */
    private function current(): ?Church
    {
        $current = app()->bound('current.church') ? app('current.church') : null;

        return $current instanceof Church ? $current : null;
    }
}
