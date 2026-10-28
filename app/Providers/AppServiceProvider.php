<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\ResolveTenant;
use App\Models\PersonalAccessToken;
use App\ValueObjects\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Date::use(CarbonImmutable::class);
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // I fail loudly in dev and tests so an N+1 query is caught before it reaches production.
        Model::preventLazyLoading(! $this->app->isProduction());

        $this->resolveTenantFirst();
        $this->configureRateLimiting();
    }

    /**
     * Makes the tenant middleware run before everything else, including Sanctum's session handling.
     * I do it here, not in bootstrap/app.php, because Sanctum puts its own middleware at the front of
     * the priority list while it boots; this provider boots after it, so ours ends up in front.
     * Without this, a user could be loaded before the church is known and leak across churches.
     */
    private function resolveTenantFirst(): void
    {
        $this->app->make(Kernel::class)->prependToMiddlewarePriority(ResolveTenant::class);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(300)->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        // Sending a code costs money, so it gets its own tight limits on top of the security ones:
        // 1 a minute and 5 an hour per phone, plus a ceiling per IP.
        RateLimiter::for('otp-request', function (Request $request): array {
            $phone = PhoneNumber::tryFrom((string) $request->input('phone'))->e164 ?? 'invalid';
            $key = $this->tenantKey($request).':'.hash('sha256', $phone);

            return [
                Limit::perMinute(1)->by($key),
                Limit::perHour(5)->by($key),
                Limit::perHour(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinute(10)->by($this->tenantKey($request).':'.$request->ip()));
    }

    private function tenantKey(Request $request): string
    {
        return $this->app->bound('current.church') ? (string) app('current.church')->getKey() : $request->getHost();
    }
}
