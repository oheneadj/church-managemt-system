<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// The single page the React app mounts into (D-022). Every path that is not the API, a framework
// endpoint or Telescope falls through to it, and React Router decides what to show.
// 'tenant' makes an unknown church subdomain a 404 here too, before any JavaScript loads.
Route::view('/{any?}', 'app')
    ->where('any', '^(?!api|up|telescope|sanctum).*$')
    ->middleware('tenant')
    ->name('spa');
