<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\StaffPermission;
use App\Exceptions\MisconfiguredEnvironmentException;
use App\Http\Middleware\ResolveTenant;
use App\Models\Department;
use App\Models\Family;
use App\Models\Member;
use App\Models\PersonalAccessToken;
use App\Models\Service;
use App\Models\User;
use App\Services\HealthCheck;
use App\Support\EnvironmentCheck;
use App\ValueObjects\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

/**
 * Application-wide setup: the tenant middleware order, route model bindings, date and lazy-loading rules,
 * rate limiters, the environment guard and the local-only Telescope.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(HealthCheck::class, fn () => HealthCheck::standard());

        // Telescope is a dev-only package (docs/15: none in production), so it is registered here only when it is
        // installed and we are running locally. Registering it in bootstrap/providers.php would crash a
        // `composer install --no-dev` build, because the class would not exist.
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The Form Requests read these models from the route, and single-action controllers do not type-hint them,
        // so I bind them explicitly. The church scope still applies, so another church's id is a 404.
        Route::model('member', Member::class);
        Route::model('family', Family::class);
        Route::model('department', Department::class);
        Route::model('service', Service::class);

        // The interactive API docs are open locally and, everywhere else, only for staff who may change church settings.
        Gate::define('viewApiDocs', fn (?User $user = null): bool => $user?->can(StaffPermission::ChurchSettings->value) === true);

        Date::use(CarbonImmutable::class);
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // I fail loudly in dev and tests so an N+1 query is caught before it reaches production.
        Model::preventLazyLoading(! $this->app->isProduction());

        $this->failFastOnBadEnvironment();
        $this->resolveTenantFirst();

        // Every log line says which service and environment wrote it, as the JSON log format requires.
        Context::add('service', 'chms');
        Context::add('environment', $this->app->environment());
        $this->configureRateLimiting();
    }

    /**
     * Stops a deployed web request from running on a broken configuration. Console commands are exempt
     * so the image can be built without production secrets; the container runs `env:check` at start-up.
     */
    private function failFastOnBadEnvironment(): void
    {
        if ($this->app->runningInConsole() || ! $this->app->environment(['production', 'staging'])) {
            return;
        }

        $problems = EnvironmentCheck::problems($this->app->make('config'));

        if ($problems !== []) {
            throw new MisconfiguredEnvironmentException($problems);
        }
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

    /**
     * Defines the named rate limiters, including the tight limits on costly actions.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(config('limits.api_per_minute'))->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        // Sending a code costs money, so it gets its own tight limits on top of the security ones:
        // The limits per phone number and per IP address are set in config/limits.php.
        RateLimiter::for('otp-request', function (Request $request): array {
            $phone = PhoneNumber::tryFrom((string) $request->input('phone'))->e164 ?? 'invalid';
            $key = $this->tenantKey($request).':'.hash('sha256', $phone);

            return [
                Limit::perMinute(config('limits.otp_request_per_minute'))->by($key),
                Limit::perHour(config('limits.otp_request_per_hour'))->by($key),
                Limit::perHour(config('limits.otp_request_per_hour_per_ip'))->by($request->ip()),
            ];
        });

        // Starting a church texts a code to a stranger's phone, so it is limited per number and per IP address.
        RateLimiter::for('onboarding-otp', function (Request $request): array {
            $phone = PhoneNumber::tryFrom((string) $request->input('admin_phone'))->e164 ?? 'invalid';
            $key = 'onboarding:'.hash('sha256', $phone);

            return [
                Limit::perMinute(config('limits.onboarding_otp_per_minute'))->by($key),
                Limit::perHour(config('limits.onboarding_otp_per_hour'))->by($key),
                Limit::perHour(config('limits.onboarding_otp_per_hour_per_ip'))->by($request->ip()),
            ];
        });

        RateLimiter::for('onboarding-register', fn (Request $request) => Limit::perHour(config('limits.onboarding_register_per_hour_per_ip'))->by($request->ip()));

        // A member checking in by QR code is texted a code, so it is limited per phone and service, and per IP address.
        RateLimiter::for('checkin-otp', function (Request $request): array {
            $phone = PhoneNumber::tryFrom((string) $request->input('phone'))->e164 ?? 'invalid';
            $key = $this->tenantKey($request).':'.hash('sha256', $phone.'|'.$request->input('token'));

            return [
                Limit::perMinute(config('limits.checkin_otp_per_minute'))->by($key),
                Limit::perHour(config('limits.checkin_otp_per_hour'))->by($key),
                Limit::perHour(config('limits.checkin_otp_per_hour_per_ip'))->by($this->tenantKey($request).':'.$request->ip()),
            ];
        });

        RateLimiter::for('checkin-verify', fn (Request $request) => Limit::perMinute(config('limits.checkin_verify_per_minute_per_ip'))->by($this->tenantKey($request).':'.$request->ip()));

        // Uploads are CPU heavy (every photo is re-encoded), so they get a tighter limit than other calls.
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(config('limits.uploads_per_minute'))->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinute(config('limits.otp_verify_per_minute_per_ip'))->by($this->tenantKey($request).':'.$request->ip()));
    }

    /**
     * The church id (or the host) used to keep each church's rate limits separate.
     */
    private function tenantKey(Request $request): string
    {
        return $this->app->bound('current.church') ? (string) app('current.church')->getKey() : $request->getHost();
    }
}
