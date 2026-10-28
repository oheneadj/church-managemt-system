<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RequestOtpController;
use App\Http\Controllers\Api\V1\Auth\VerifyOtpController;
use App\Http\Controllers\Api\V1\FamilyController;
use App\Http\Controllers\Api\V1\MemberController;
use Illuminate\Support\Facades\Route;

// Versioned API routes (prefix /api/v1, set in bootstrap/app.php).
// Every group must use the 'tenant' middleware; see docs/08-api.md.

Route::middleware('tenant')->group(function (): void {
    // Public: sign-in. Both are rate limited; the request limiter also protects SMS cost.
    Route::post('auth/otp/request', RequestOtpController::class)->middleware('throttle:otp-request')->name('auth.otp.request');
    Route::post('auth/otp/verify', VerifyOtpController::class)->middleware('throttle:otp-verify')->name('auth.otp.verify');

    Route::middleware(['auth:sanctum', 'staff.current'])->group(function (): void {
        Route::post('auth/logout', LogoutController::class)->name('auth.logout');
        Route::get('auth/me', MeController::class)->name('auth.me');

        Route::get('members', [MemberController::class, 'index'])->name('members.index');
        Route::post('members', [MemberController::class, 'store'])->name('members.store');
        Route::get('members/{member}', [MemberController::class, 'show'])->name('members.show');
        Route::put('members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::patch('members/{member}/status', [MemberController::class, 'updateStatus'])->name('members.status');

        Route::get('families', [FamilyController::class, 'index'])->name('families.index');
        Route::post('families', [FamilyController::class, 'store'])->name('families.store');
        Route::get('families/{family}', [FamilyController::class, 'show'])->name('families.show');
        Route::put('families/{family}', [FamilyController::class, 'update'])->name('families.update');
    });
});
