<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Attendance\QrCode;
use App\Models\Church;
use App\Models\Service;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Makes and checks the short-lived codes shown as a QR code at the venue. A code is a random string that points to one
 * service in one church, kept in the cache for a couple of minutes; it carries no data itself, so it cannot be forged
 * or edited. The key includes the church, so one church's code never opens another church's service.
 */
class QrTokenService
{
    /**
     * Makes a new code for the service. Older codes stay valid until they expire, so a member who is just about to scan
     * is not turned away when the screen refreshes.
     */
    public function issue(Church $church, Service $service): QrCode
    {
        $token = Str::random((int) config('attendance.qr_token_length'));
        $ttl = (int) config('attendance.qr_ttl_seconds');

        Cache::put($this->key($church, $token), $service->id, $ttl);

        return new QrCode($token, now()->addSeconds($ttl)->toImmutable());
    }

    /**
     * The id of the service a code points to, or null when the code is unknown, expired or from another church.
     */
    public function serviceIdFor(Church $church, string $token): ?string
    {
        $serviceId = Cache::get($this->key($church, $token));

        return is_string($serviceId) ? $serviceId : null;
    }

    /**
     * The cache key of a code, namespaced by church (agreement section 34).
     */
    private function key(Church $church, string $token): string
    {
        return "church:{$church->id}:qr:".hash('sha256', $token);
    }
}
