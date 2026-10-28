<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Paths that belong to the API, the framework or a tool. Everything else is a screen of the React app.
$notReactScreens = ['api', 'up', 'docs', 'telescope', 'sanctum', 'storage'];

// The single page the React app mounts into (D-022). Every other path falls through to it, and React Router
// decides what to show. 'tenant' makes an unknown church subdomain a 404 here too, before any JavaScript loads.
// The platform's own host (no church) serves the same shell so the "start a church" page can load. It needs no tenant.
Route::domain(config('tenancy.base_domain'))->group(function () use ($notReactScreens): void {
    Route::view('/{any?}', 'app')
        ->where('any', '^(?!'.implode('|', $notReactScreens).').*$')
        ->name('spa.platform');
});

Route::view('/{any?}', 'app')
    ->where('any', '^(?!'.implode('|', $notReactScreens).').*$')
    ->middleware('tenant')
    ->name('spa');
