<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Attendance\CaptureVisitorController;
use App\Http\Controllers\Api\V1\Attendance\IssueQrCodeController;
use App\Http\Controllers\Api\V1\Attendance\ListAbsentMembersController;
use App\Http\Controllers\Api\V1\Attendance\ListServiceAttendanceController;
use App\Http\Controllers\Api\V1\Attendance\QrCheckInController;
use App\Http\Controllers\Api\V1\Attendance\RecordAttendanceController;
use App\Http\Controllers\Api\V1\Attendance\RequestQrCheckinOtpController;
use App\Http\Controllers\Api\V1\Attendance\ShowMemberAttendanceController;
use App\Http\Controllers\Api\V1\Attendance\ShowQrServiceController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RequestOtpController;
use App\Http\Controllers\Api\V1\Auth\VerifyOtpController;
use App\Http\Controllers\Api\V1\Departments\AddDepartmentMemberController;
use App\Http\Controllers\Api\V1\Departments\ListDepartmentsController;
use App\Http\Controllers\Api\V1\Departments\RemoveDepartmentMemberController;
use App\Http\Controllers\Api\V1\Departments\StoreDepartmentController;
use App\Http\Controllers\Api\V1\Departments\UpdateDepartmentController;
use App\Http\Controllers\Api\V1\Families\ListFamiliesController;
use App\Http\Controllers\Api\V1\Families\ShowFamilyController;
use App\Http\Controllers\Api\V1\Families\StoreFamilyController;
use App\Http\Controllers\Api\V1\Families\UpdateFamilyController;
use App\Http\Controllers\Api\V1\Members\EraseMemberController;
use App\Http\Controllers\Api\V1\Members\ExportMemberDataController;
use App\Http\Controllers\Api\V1\Members\ListMembersController;
use App\Http\Controllers\Api\V1\Members\ShowMemberController;
use App\Http\Controllers\Api\V1\Members\StoreMemberController;
use App\Http\Controllers\Api\V1\Members\StoreMemberMilestoneController;
use App\Http\Controllers\Api\V1\Members\UpdateMemberController;
use App\Http\Controllers\Api\V1\Members\UpdateMemberStatusController;
use App\Http\Controllers\Api\V1\Members\UploadMemberPhotoController;
use App\Http\Controllers\Api\V1\Onboarding\RegisterChurchController;
use App\Http\Controllers\Api\V1\Onboarding\RequestChurchOtpController;
use App\Http\Controllers\Api\V1\Services\ListServicesController;
use App\Http\Controllers\Api\V1\Services\SetServiceCheckinController;
use App\Http\Controllers\Api\V1\Services\ShowServiceController;
use App\Http\Controllers\Api\V1\Services\StoreServiceController;
use App\Http\Controllers\Api\V1\Services\UpdateServiceController;
use Illuminate\Support\Facades\Route;

// Versioned API routes (prefix /api/v1, set in bootstrap/app.php).
// Every group must use the 'tenant' middleware; see docs/08-api.md.

// Starting a church happens on the platform's own host (the bare base domain), before any church exists, so
// these routes sit outside the 'tenant' group and are not reachable on a church's subdomain.
Route::domain(config('tenancy.base_domain'))->prefix('onboarding')->name('onboarding.')->group(function (): void {
    Route::post('otp', RequestChurchOtpController::class)->middleware('throttle:onboarding-otp')->name('otp');
    Route::post('churches', RegisterChurchController::class)->middleware('throttle:onboarding-register')->name('churches.store');
});

Route::middleware('tenant')->group(function (): void {
    // Public: sign-in. Both are rate limited; the request limiter also protects SMS cost.
    Route::post('auth/otp/request', RequestOtpController::class)->middleware('throttle:otp-request')->name('auth.otp.request');
    Route::post('auth/otp/verify', VerifyOtpController::class)->middleware('throttle:otp-verify')->name('auth.otp.verify');

    // Public: a member who scanned the QR code at the venue. The code is the proof of presence; a texted code proves the phone.
    Route::get('checkin/qr/{token}', ShowQrServiceController::class)->middleware('throttle:checkin-verify')->name('checkin.qr.show');
    Route::post('checkin/qr/otp', RequestQrCheckinOtpController::class)->middleware('throttle:checkin-otp')->name('checkin.qr.otp');
    Route::post('checkin/qr', QrCheckInController::class)->middleware('throttle:checkin-verify')->name('checkin.qr.store');

    Route::middleware(['auth:sanctum', 'staff.current', 'monitor.context'])->group(function (): void {
        Route::post('auth/logout', LogoutController::class)->name('auth.logout');
        Route::get('auth/me', MeController::class)->name('auth.me');

        Route::get('members', ListMembersController::class)->name('members.index');
        Route::post('members', StoreMemberController::class)->name('members.store');
        Route::get('members/{member}', ShowMemberController::class)->name('members.show');
        Route::put('members/{member}', UpdateMemberController::class)->name('members.update');
        Route::get('members/{member}/data-export', ExportMemberDataController::class)->name('members.data-export');
        Route::post('members/{member}/erasure', EraseMemberController::class)->name('members.erase');
        Route::patch('members/{member}/status', UpdateMemberStatusController::class)->name('members.status');
        Route::post('members/{member}/photo', UploadMemberPhotoController::class)->middleware('throttle:uploads')->name('members.photo');
        Route::post('members/{member}/milestones', StoreMemberMilestoneController::class)->name('members.milestones.store');

        Route::get('families', ListFamiliesController::class)->name('families.index');
        Route::post('families', StoreFamilyController::class)->name('families.store');
        Route::get('families/{family}', ShowFamilyController::class)->name('families.show');
        Route::put('families/{family}', UpdateFamilyController::class)->name('families.update');

        Route::get('services', ListServicesController::class)->name('services.index');
        Route::post('services', StoreServiceController::class)->name('services.store');
        Route::get('services/{service}', ShowServiceController::class)->name('services.show');
        Route::put('services/{service}', UpdateServiceController::class)->name('services.update');
        Route::patch('services/{service}/checkin', SetServiceCheckinController::class)->name('services.checkin');

        Route::get('services/{service}/attendance', ListServiceAttendanceController::class)->name('attendance.index');
        Route::post('services/{service}/attendance', RecordAttendanceController::class)->name('attendance.record');
        Route::post('services/{service}/visitors', CaptureVisitorController::class)->name('visitors.store');
        Route::get('services/{service}/qr', IssueQrCodeController::class)->name('services.qr');
        Route::get('attendance/absent', ListAbsentMembersController::class)->name('attendance.absent');
        Route::get('members/{member}/attendance', ShowMemberAttendanceController::class)->name('members.attendance');

        Route::get('departments', ListDepartmentsController::class)->name('departments.index');
        Route::post('departments', StoreDepartmentController::class)->name('departments.store');
        Route::put('departments/{department}', UpdateDepartmentController::class)->name('departments.update');
        Route::post('departments/{department}/members', AddDepartmentMemberController::class)->name('departments.members.add');
        Route::delete('departments/{department}/members/{member}', RemoveDepartmentMemberController::class)->name('departments.members.remove');
    });
});
