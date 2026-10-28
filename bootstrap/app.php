<?php

declare(strict_types=1);

use App\Http\Middleware\AddErrorMonitoringContext;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureStaffIsCurrent;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
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
        $middleware->alias(['tenant' => ResolveTenant::class, 'staff.current' => EnsureStaffIsCurrent::class, 'monitor.context' => AddErrorMonitoringContext::class]);
        // The church must be known before auth runs, so Sanctum can only find users of this church.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sends unhandled errors to the error monitor (docs/17). It does nothing until SENTRY_LARAVEL_DSN is set.
        Integration::handles($exceptions);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
