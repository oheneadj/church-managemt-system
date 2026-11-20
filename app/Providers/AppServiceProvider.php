<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\StaffPermission;
use App\Exceptions\MisconfiguredEnvironmentException;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\ResolveTenant;
use App\Models\Contribution;
use App\Models\Department;
use App\Models\Family;
use App\Models\FundType;
use App\Models\Member;
use App\Models\PersonalAccessToken;
use App\Models\Pledge;
use App\Models\Service;
use App\Models\ServiceSchedule;
use App\Models\SmsCreditPurchase;
use App\Models\SmsPackage;
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
        Route::model('fund_type', FundType::class);
        Route::model('sms_credit_purchase', SmsCreditPurchase::class);
        Route::model('sms_package', SmsPackage::class);
        Route::model('contribution', Contribution::class);
        Route::model('pledge', Pledge::class);
        Route::model('service_schedule', ServiceSchedule::class);
        Route::model('service', Service::class);
        Route::model('staff', User::class);

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

        // A broadcast texts many people and costs credits, so a church may send a few an hour, however many staff send them.
        RateLimiter::for('credit-purchase', fn (Request $request) => Limit::perHour(config('limits.credit_purchases_per_hour'))
            ->by($this->tenantKey($request))
            ->response(fn (Request $request, array $headers) => response()->json(['message' => 'You can start at most '.config('limits.credit_purchases_per_hour').' credit purchases per hour.'], 429, $headers)));

        // A payment prompt goes to someone's phone, so one staff member may start only a few a minute.
        RateLimiter::for('gift-initiate', fn (Request $request) => Limit::perMinute(config('limits.gift_initiations_per_minute'))
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip())
            ->response(fn (Request $request, array $headers) => response()->json(['message' => 'You can start at most '.config('limits.gift_initiations_per_minute').' gift payments per minute.'], 429, $headers)));

        RateLimiter::for('public-give-page', fn (Request $request) => Limit::perMinute(config('limits.public_give_page_per_minute'))
            ->by($request->ip())
            ->response(fn (Request $request, array $headers) => response()->json(['message' => 'Too many people are opening this page from here right now. Please wait a moment and try again.'], 429, $headers)));

        // One payment prompt to a phone every so many seconds, whoever asks: a rate and not a total, so a person on a poor network can
        // keep trying a minute apart. The key is the church and a hash of the number, so the limiter never holds the number itself.
        RateLimiter::for('public-give-phone', function (Request $request): Limit {
            // A retry of an attempt that was already answered only gets that answer again, so it is never counted as a new prompt.
            if (EnsureIdempotency::isReplay($request)) {
                return Limit::none();
            }

            $phone = PhoneNumber::tryFrom((string) $request->input('phone'))->e164 ?? (string) $request->input('phone');

            return Limit::perSecond(1, config('limits.public_give_phone_seconds'))
                ->by('give-phone:'.app('current.church')->getKey().':'.hash('sha256', $phone))
                ->response(fn (Request $request, array $headers) => response()->json(['message' => 'A payment prompt was just sent to this number. Please wait a minute before asking for another.'], 429, $headers));
        });

        // Starting a gift from the public page is limited by internet address, and high, because a whole congregation shares one.
        RateLimiter::for('public-give-address', fn (Request $request) => EnsureIdempotency::isReplay($request) ? Limit::none() : Limit::perHour(config('limits.public_give_address_per_hour'))
            ->by('give-address:'.$request->ip())
            ->response(fn (Request $request, array $headers) => response()->json(['message' => 'Too many gifts are being started from here right now. Please wait a few minutes and try again.'], 429, $headers)));

        RateLimiter::for('broadcast', fn (Request $request) => Limit::perHour(config('limits.broadcasts_per_hour'))
            ->by($this->tenantKey($request))
            ->response(fn (Request $request, array $headers) => response()->json(['message' => 'You can send at most '.config('limits.broadcasts_per_hour').' broadcasts per hour.'], 429, $headers)));

        // An export reads a lot of personal data and uses a worker, so each staff member may start a few an hour (QA-11 TC-11-12).
        RateLimiter::for('exports', fn (Request $request) => Limit::perHour(config('limits.exports_per_hour'))
            ->by((string) $request->user()?->getAuthIdentifier())
            ->response(fn (Request $request, array $headers) => response()->json(['message' => 'You can export at most '.config('limits.exports_per_hour').' times per hour.'], 429, $headers)));

        // Inviting staff emails a code to a stranger's address, so it is limited for the whole church (numbers in config/limits.php).
        RateLimiter::for('staff-invite', fn (Request $request) => Limit::perHour(config('limits.staff_invites_per_hour'))->by($this->tenantKey($request)));

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
        // Meta's servers call the webhook, so it is limited per address and generously (the signature is the real guard).
        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(config('limits.webhooks_per_minute'))->by($request->ip()));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(config('limits.uploads_per_minute'))->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        // Staff sign-in by email (D-054): guessing passwords and checking emailed codes are limited per address and church.
        // The limit on how many codes are emailed is counted only when one is sent, inside EmailCodeRequestLimiter.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(config('limits.login_attempts_per_minute_per_ip'))->by($this->tenantKey($request).':'.$request->ip()));

        // The platform admin side has no church to key on, so its limits are by address alone.
        RateLimiter::for('platform-login', fn (Request $request) => Limit::perMinute(config('limits.login_attempts_per_minute_per_ip'))->by('platform:'.$request->ip()));

        RateLimiter::for('platform-login-verify', fn (Request $request) => Limit::perMinute(config('limits.login_verify_per_minute_per_ip'))->by('platform:'.$request->ip()));

        RateLimiter::for('login-verify', fn (Request $request) => Limit::perMinute(config('limits.login_verify_per_minute_per_ip'))->by($this->tenantKey($request).':'.$request->ip()));

    }

    /**
     * The church id (or the host) used to keep each church's rate limits separate.
     */
    private function tenantKey(Request $request): string
    {
        return $this->app->bound('current.church') ? (string) app('current.church')->getKey() : $request->getHost();
    }
}
