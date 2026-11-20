<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Church;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Limits how many emails with a code can be asked for (agreement section 16: a cost-triggering action has its own limit,
 * apart from the security limits). It is checked and counted only when a code is really about to be sent, so a mistyped
 * password never uses up the allowance: one a minute and five an hour for one email, 20 an hour from one address.
 */
class EmailCodeRequestLimiter
{
    /**
     * Counts one code request, or throws a 429 with how long to wait when any allowance is used up.
     *
     * @throws ThrottleRequestsException
     */
    public function attempt(Church $church, string $email, string $ip): void
    {
        $subject = $church->id.':'.hash('sha256', $email);
        $limits = [
            ["login-code-minute:{$subject}", (int) config('limits.login_code_per_minute'), 60],
            ["login-code-hour:{$subject}", (int) config('limits.login_code_per_hour'), 3600],
            ["login-code-ip-hour:{$church->id}:{$ip}", (int) config('limits.login_code_per_hour_per_ip'), 3600],
        ];

        foreach ($limits as [$key, $allowed]) {
            if (RateLimiter::tooManyAttempts($key, $allowed)) {
                throw new ThrottleRequestsException('Too many requests.', null, ['Retry-After' => (string) RateLimiter::availableIn($key)]);
            }
        }

        foreach ($limits as [$key, , $seconds]) {
            RateLimiter::hit($key, $seconds);
        }
    }
}
