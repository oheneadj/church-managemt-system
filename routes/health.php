<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Operational endpoints. Deliberately unversioned (the deploy gate and uptime monitors call a fixed URL),
// public, and without the tenant middleware: they are about the platform, not any one church.
Route::get('health', HealthController::class)->name('health');
