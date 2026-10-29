<?php

declare(strict_types=1);

use App\Exceptions\ChurchSlugTakenException;
use App\Exceptions\ContributionAlreadyVoidedException;
use App\Exceptions\FieldRuleException;
use App\Exceptions\InvalidOtpException;
use App\Exceptions\MemberNumberPrefixMissingException;
use App\Exceptions\ServiceHasRecordsException;
use App\Exceptions\ServiceIsOpenException;
use App\Exceptions\TooManyOtpAttemptsException;
use App\Http\Middleware\AddErrorMonitoringContext;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\EnsureStaffIsCurrent;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyMetaSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')->prefix('api')->group(base_path('routes/health.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(SecurityHeaders::class);

        // Browser requests from a church's own host get session cookies; everything else keeps bearer tokens.
        $middleware->statefulApi();
        $middleware->throttleApi('api');
        $middleware->alias(['tenant' => ResolveTenant::class, 'staff.current' => EnsureStaffIsCurrent::class, 'monitor.context' => AddErrorMonitoringContext::class, 'meta.signature' => VerifyMetaSignature::class, 'idempotent' => EnsureIdempotency::class]);
        // The church must be known before auth runs, so Sanctum can only find users of this church.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sends unhandled errors to the error monitor (docs/17). It does nothing until SENTRY_LARAVEL_DSN is set.
        Integration::handles($exceptions);

        // A wrong code, a taken address or a rule the user broke is not a fault of ours. These get their 4xx answer
        // but are not written to the log as errors and not sent to the error monitor.
        $exceptions->dontReport([
            InvalidOtpException::class,
            TooManyOtpAttemptsException::class,
            ChurchSlugTakenException::class,
            FieldRuleException::class,
            ContributionAlreadyVoidedException::class,
            ServiceHasRecordsException::class,
            ServiceIsOpenException::class,
            MemberNumberPrefixMissingException::class,
        ]);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
