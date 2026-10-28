<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Paths that belong to the API, the framework or a tool. Everything else is a screen of the React app.
$notReactScreens = ['api', 'up', 'docs', 'telescope', 'sanctum', 'storage'];

// The single page the React app mounts into (D-022). Every other path falls through to it, and React Router
// decides what to show. 'tenant' makes an unknown church subdomain a 404 here too, before any JavaScript loads.
Route::view('/{any?}', 'app')
    ->where('any', '^(?!'.implode('|', $notReactScreens).').*$')
    ->middleware('tenant')
    ->name('spa');
