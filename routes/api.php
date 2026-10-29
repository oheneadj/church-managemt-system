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
use App\Http\Controllers\Api\V1\Contributions\ListContributionsController;
use App\Http\Controllers\Api\V1\Contributions\ShowContributionController;
use App\Http\Controllers\Api\V1\Contributions\StoreContributionController;
use App\Http\Controllers\Api\V1\Contributions\VoidContributionController;
use App\Http\Controllers\Api\V1\Departments\AddDepartmentMemberController;
use App\Http\Controllers\Api\V1\Departments\ListDepartmentsController;
use App\Http\Controllers\Api\V1\Departments\RemoveDepartmentMemberController;
use App\Http\Controllers\Api\V1\Departments\StoreDepartmentController;
use App\Http\Controllers\Api\V1\Departments\UpdateDepartmentController;
use App\Http\Controllers\Api\V1\Families\ListFamiliesController;
use App\Http\Controllers\Api\V1\Families\ShowFamilyController;
use App\Http\Controllers\Api\V1\Families\StoreFamilyController;
use App\Http\Controllers\Api\V1\Families\UpdateFamilyController;
use App\Http\Controllers\Api\V1\FundTypes\ListFundTypesController;
use App\Http\Controllers\Api\V1\FundTypes\StoreFundTypeController;
use App\Http\Controllers\Api\V1\FundTypes\UpdateFundTypeController;
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
use App\Http\Controllers\Api\V1\Pledges\ListPledgesController;
use App\Http\Controllers\Api\V1\Pledges\ShowPledgeController;
use App\Http\Controllers\Api\V1\Pledges\StorePledgeController;
use App\Http\Controllers\Api\V1\Pledges\UpdatePledgeController;
use App\Http\Controllers\Api\V1\Services\CancelServiceController;
use App\Http\Controllers\Api\V1\Services\ListServicesController;
use App\Http\Controllers\Api\V1\Services\SetServiceCheckinController;
use App\Http\Controllers\Api\V1\Services\ShowOfferingReportController;
use App\Http\Controllers\Api\V1\Services\ShowServiceController;
use App\Http\Controllers\Api\V1\Services\StoreServiceController;
use App\Http\Controllers\Api\V1\Services\UpdateServiceController;
use App\Http\Controllers\Api\V1\ServiceSchedules\ListServiceSchedulesController;
use App\Http\Controllers\Api\V1\ServiceSchedules\StoreServiceScheduleController;
use App\Http\Controllers\Api\V1\ServiceSchedules\UpdateServiceScheduleController;
use App\Http\Controllers\Api\V1\Webhooks\ReceiveWhatsAppWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\VerifyWhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

// Versioned API routes (prefix /api/v1, set in bootstrap/app.php).
// Every group must use the 'tenant' middleware; see docs/08-api.md.

// Starting a church happens on the platform's own host (the bare base domain), before any church exists, so
// these routes sit outside the 'tenant' group and are not reachable on a church's subdomain.
Route::domain(config('tenancy.base_domain'))->prefix('onboarding')->name('onboarding.')->group(function (): void {
    Route::post('otp', RequestChurchOtpController::class)->middleware('throttle:onboarding-otp')->name('otp');
    Route::post('churches', RegisterChurchController::class)->middleware('throttle:onboarding-register')->name('churches.store');
});

// Meta calls one address for every church, so the webhook lives on the platform host with no church in the request (docs/08, Webhooks).
Route::domain(config('tenancy.base_domain'))->prefix('webhooks')->name('webhooks.')->group(function (): void {
    Route::get('whatsapp', VerifyWhatsAppWebhookController::class)->name('whatsapp.verify');
    Route::post('whatsapp', ReceiveWhatsAppWebhookController::class)->middleware(['throttle:webhooks', 'meta.signature'])->name('whatsapp.receive');
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

        Route::get('service-schedules', ListServiceSchedulesController::class)->name('service-schedules.index');
        Route::post('service-schedules', StoreServiceScheduleController::class)->middleware('idempotent')->name('service-schedules.store');
        Route::put('service-schedules/{service_schedule}', UpdateServiceScheduleController::class)->name('service-schedules.update');

        Route::get('services', ListServicesController::class)->name('services.index');
        Route::post('services', StoreServiceController::class)->name('services.store');
        Route::get('services/{service}', ShowServiceController::class)->name('services.show');
        Route::put('services/{service}', UpdateServiceController::class)->name('services.update');
        Route::delete('services/{service}', CancelServiceController::class)->name('services.cancel');
        Route::patch('services/{service}/checkin', SetServiceCheckinController::class)->name('services.checkin');

        Route::get('services/{service}/attendance', ListServiceAttendanceController::class)->name('attendance.index');
        Route::post('services/{service}/attendance', RecordAttendanceController::class)->name('attendance.record');
        Route::post('services/{service}/visitors', CaptureVisitorController::class)->name('visitors.store');
        Route::get('services/{service}/qr', IssueQrCodeController::class)->name('services.qr');
        Route::get('services/{service}/offering-report', ShowOfferingReportController::class)->name('services.offering-report');
        Route::get('attendance/absent', ListAbsentMembersController::class)->name('attendance.absent');
        Route::get('members/{member}/attendance', ShowMemberAttendanceController::class)->name('members.attendance');

        Route::get('contributions', ListContributionsController::class)->name('contributions.index');
        Route::post('contributions', StoreContributionController::class)->middleware('idempotent')->name('contributions.store');
        Route::get('contributions/{contribution}', ShowContributionController::class)->name('contributions.show');
        Route::patch('contributions/{contribution}/void', VoidContributionController::class)->name('contributions.void');

        Route::get('pledges', ListPledgesController::class)->name('pledges.index');
        Route::post('pledges', StorePledgeController::class)->middleware('idempotent')->name('pledges.store');
        Route::get('pledges/{pledge}', ShowPledgeController::class)->name('pledges.show');
        Route::put('pledges/{pledge}', UpdatePledgeController::class)->name('pledges.update');

        Route::get('fund-types', ListFundTypesController::class)->name('fund-types.index');
        Route::post('fund-types', StoreFundTypeController::class)->name('fund-types.store');
        Route::put('fund-types/{fund_type}', UpdateFundTypeController::class)->name('fund-types.update');
        Route::patch('fund-types/{fund_type}', UpdateFundTypeController::class)->name('fund-types.patch');

        Route::get('departments', ListDepartmentsController::class)->name('departments.index');
        Route::post('departments', StoreDepartmentController::class)->name('departments.store');
        Route::put('departments/{department}', UpdateDepartmentController::class)->name('departments.update');
        Route::post('departments/{department}/members', AddDepartmentMemberController::class)->name('departments.members.add');
        Route::delete('departments/{department}/members/{member}', RemoveDepartmentMemberController::class)->name('departments.members.remove');
    });
});
