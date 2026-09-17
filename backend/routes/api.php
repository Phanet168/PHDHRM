<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceAccessRequestController;
use App\Http\Controllers\Api\ExternalSyncController;
use Modules\HumanResource\Http\Controllers\ShiftController;
use Modules\HumanResource\Http\Controllers\ShiftRosterController;
use Modules\HumanResource\Http\Controllers\MissionController;
use Modules\HumanResource\Http\Controllers\AttendanceAdjustmentController;
use Modules\HumanResource\Http\Controllers\AttendanceSnapshotController;
use Modules\HumanResource\Http\Controllers\MobileAttendanceController;
use Modules\HumanResource\Http\Controllers\LeaveRequestApiController;
use Modules\HumanResource\Http\Controllers\NoticeNotificationApiController;
use Modules\Correspondence\Http\Controllers\CorrespondenceController;
use App\Http\Controllers\Api\CapabilityController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', [AuthController::class, 'sanctumUser'])->name('api.user');

Route::get('/', [ApiController::class, 'index']);
Route::get('/missions/{id}/documents/{document}/download', [MissionController::class, 'signedDownload'])
    ->whereNumber('id')->whereNumber('document')->middleware('signed')->name('api.missions.documents.signed');
Route::post('/auth/login', [AuthController::class, 'login'])->name('api.auth.login');

// Legacy device access request endpoints (kept for backward compatibility)
Route::post('/device-access-requests', [DeviceAccessRequestController::class, 'store'])->name('api.device-access-requests.store');
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/device-access-requests', [DeviceAccessRequestController::class, 'index'])->name('api.device-access-requests.index');
    Route::patch('/device-access-requests/{deviceAccessRequest}/review', [DeviceAccessRequestController::class, 'review'])->name('api.device-access-requests.review');
});

// New device registration workflow endpoints
Route::post('/auth/device-request', [AuthController::class, 'requestDeviceAccess'])->name('api.auth.device_request');
// Backward-compatible aliases for old Flutter builds
Route::post('/auth/device_request', [AuthController::class, 'requestDeviceAccess'])->name('api.auth.device_request_legacy');
Route::post('/device-request', [AuthController::class, 'requestDeviceAccess'])->name('api.device_request');
Route::post('/device_request', [AuthController::class, 'requestDeviceAccess'])->name('api.device_request_legacy');
Route::post('/auth/device-request-status', [AuthController::class, 'deviceRequestStatus'])->name('api.auth.device_request_status');
Route::post('/auth/device_request_status', [AuthController::class, 'deviceRequestStatus'])->name('api.auth.device_request_status_legacy');
Route::post('/device-request-status', [AuthController::class, 'deviceRequestStatus'])->name('api.device_request_status');
Route::post('/device_request_status', [AuthController::class, 'deviceRequestStatus'])->name('api.device_request_status_legacy');
Route::middleware('auth:sanctum')->post('/auth/device-heartbeat', [AuthController::class, 'deviceHeartbeat'])->name('api.auth.device_heartbeat');
Route::middleware('auth:sanctum')->post('/auth/device_heartbeat', [AuthController::class, 'deviceHeartbeat'])->name('api.auth.device_heartbeat_legacy');
Route::middleware('auth:sanctum')->match(['get', 'post'], '/auth/profile', [AuthController::class, 'profile'])->name('api.auth.profile');
Route::middleware('auth:sanctum')->post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
Route::middleware('auth:sanctum')->post('/auth/change-password', [AuthController::class, 'changePassword'])->name('api.auth.change_password');

Route::controller(ApiController::class)->group(function () {

    Route::get('/language', 'language')->name('language');
    Route::get('/webSetting', 'webSetting')->name('webSetting');

    Route::match(['get', 'post'], '/login', 'login')->name('api.legacy.login');
    Route::post('/password_recovery', 'password_recovery')->name('password_recovery');

    Route::get('/recovery_form/{token_id}','recoveryForm')->name('recovery_form');
    Route::post('/recovery_submit/{token_id}','recoverySubmit')->name('recovery_submit');

    Route::match(['get', 'post'], '/add_attendance', 'addAttendance')->name('api.add_attendance');
    Route::post('/attendance_scan_log', 'attendanceScanLog')->name('api.attendance_scan_log');
    Route::get('/attendance_scan_logs', 'attendanceScanLogs')->name('api.attendance_scan_logs');

    Route::get('/attendance_history','attendanceHistory')->name('attendance_history');
    Route::get('/attendance_datewise','attendanceDatewise')->name('attendance_datewise');

    Route::get('/current_month_totalhours','currentMonthTotalHours')->name('current_month_totalhours');
    Route::get('/noticeinfo','noticeInfo')->name('noticeinfo');
    Route::get('/loan_amount','loanAmount')->name('loan_amount');
    Route::get('/graph_info','graphInfo')->name('graph_info');
    Route::get('/salary_info','salaryInfo')->name('salary_info');
    Route::get('/leave_type_list','leaveTypeList')->name('leave_type_list');
    Route::get('/leave_application','leaveApplication')->name('leave_application');
    Route::get('/leave_list','leaveList')->name('leave_list');
    Route::get('/ledger','ledger')->name('ledger');
    Route::get('/leave_remaining','leaveRemaining')->name('leave_remaining');
    Route::get('/current_month_totalday','currentMonthTotalday')->name('current_month_totalday');

});

Route::prefix('integration/v1')
    ->middleware('external.api.key')
    ->group(function () {
        Route::get('/health', [ExternalSyncController::class, 'health']);
        Route::get('/employees', [ExternalSyncController::class, 'employees']);
        Route::get('/employees/{id}', [ExternalSyncController::class, 'employee'])->whereNumber('id');
        Route::get('/departments', [ExternalSyncController::class, 'departments']);
    });

Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->group(function () {
        // Phase 2 Access Control Center foundation: additive read-only
        // capability read model. Does not replace /auth/login or
        // /auth/profile -- existing Flutter builds are unaffected.
        Route::get('/me/capabilities', [CapabilityController::class, 'me'])->name('api.v1.me.capabilities');

        Route::prefix('attendance')->name('api.v1.attendance.')->group(function () {
            Route::get('/units', [\Modules\HumanResource\Http\Controllers\AttendanceDashboardController::class, 'units'])->name('units');
            Route::get('/dashboard', [\Modules\HumanResource\Http\Controllers\AttendanceDashboardController::class, 'index'])->name('dashboard');
            Route::get('/today', [MobileAttendanceController::class, 'today'])->name('today');
            Route::get('/history', [MobileAttendanceController::class, 'history'])->name('history');
            Route::get('/schedule', [MobileAttendanceController::class, 'schedule'])->name('schedule');
            Route::post('/scan', [MobileAttendanceController::class, 'scan'])->middleware('throttle:30,1')->name('scan');
            Route::post('/scan-issues', [MobileAttendanceController::class, 'reportIssue'])->middleware('throttle:30,1')->name('scan_issues');
        });

        Route::get('/shifts', [ShiftController::class, 'index'])->name('api.v1.shifts.index');
        Route::post('/shifts', [ShiftController::class, 'store'])->name('api.v1.shifts.store');
        Route::put('/shifts/{id}', [ShiftController::class, 'update'])->whereNumber('id')->name('api.v1.shifts.update');
        Route::delete('/shifts/{id}', [ShiftController::class, 'destroy'])->whereNumber('id')->name('api.v1.shifts.destroy');

        Route::get('/shift-rosters', [ShiftRosterController::class, 'index'])->name('api.v1.shift_rosters.index');
        Route::post('/shift-rosters', [ShiftRosterController::class, 'store'])->name('api.v1.shift_rosters.store');
        Route::delete('/shift-rosters/{id}', [ShiftRosterController::class, 'destroy'])->whereNumber('id')->name('api.v1.shift_rosters.destroy');

        Route::get('/missions', [MissionController::class, 'index'])->name('api.v1.missions.index');
        Route::post('/missions', [MissionController::class, 'store'])->name('api.v1.missions.store');
        Route::get('/missions/employees', [MissionController::class, 'employees'])->name('api.v1.missions.employees');
        Route::get('/missions/types', [MissionController::class, 'types'])->name('api.v1.missions.types');
        Route::prefix('missions/{id}')->whereNumber('id')->name('api.v1.missions.')->group(function () {
            Route::get('/', [MissionController::class, 'show'])->name('show');
            Route::put('/', [MissionController::class, 'update'])->name('update');
            Route::delete('/', [MissionController::class, 'destroy'])->name('destroy');
            foreach (['review', 'cancel', 'start', 'report', 'complete'] as $action) {
                Route::post('/'.$action, [MissionController::class, $action])->name($action);
            }
            Route::post('/documents', [MissionController::class, 'upload'])->name('documents.store');
            Route::get('/documents/{document}', [MissionController::class, 'download'])->whereNumber('document')->name('documents.download');
            Route::get('/documents/{document}/signed-url', [MissionController::class, 'signedUrl'])->whereNumber('document')->name('documents.signed_url');
        });

        Route::get('/leave-types', [LeaveRequestApiController::class, 'types'])->name('api.v1.leave_types.index');
        Route::get('/leave-handover-employees', [LeaveRequestApiController::class, 'handoverEmployees'])->name('api.v1.leave_handover_employees.index');
        Route::get('/leave-requests/summary', [LeaveRequestApiController::class, 'summary'])->name('api.v1.leave_requests.summary');
        Route::get('/leave-requests/pending-review', [LeaveRequestApiController::class, 'pendingReview'])->name('api.v1.leave_requests.pending_review');
        Route::get('/leave-requests', [LeaveRequestApiController::class, 'index'])->name('api.v1.leave_requests.index');
        Route::post('/leave-requests', [LeaveRequestApiController::class, 'store'])->name('api.v1.leave_requests.store');
        Route::get('/leave-requests/{leaveRequest}', [LeaveRequestApiController::class, 'show'])->name('api.v1.leave_requests.show');
        Route::post('/leave-requests/{leaveRequest}/cancel', [LeaveRequestApiController::class, 'cancel'])->name('api.v1.leave_requests.cancel');
        Route::post('/leave-requests/{leaveRequest}/review', [LeaveRequestApiController::class, 'review'])->name('api.v1.leave_requests.review');

        Route::get('/attendance-adjustments', [AttendanceAdjustmentController::class, 'index'])->name('api.v1.attendance_adjustments.index');
        Route::post('/attendance-adjustments', [AttendanceAdjustmentController::class, 'store'])->name('api.v1.attendance_adjustments.store');

        Route::get('/attendance-snapshots/daily', [AttendanceSnapshotController::class, 'daily'])->name('api.v1.attendance_snapshots.daily');
        Route::post('/attendance-snapshots/regenerate', [AttendanceSnapshotController::class, 'regenerate'])->name('api.v1.attendance_snapshots.regenerate');

        Route::get('/notifications', [NoticeNotificationApiController::class, 'index'])->name('api.v1.notifications.index');
        Route::get('/notifications/unread-count', [NoticeNotificationApiController::class, 'unreadCount'])->name('api.v1.notifications.unread_count');
        Route::post('/notifications/read-all', [NoticeNotificationApiController::class, 'readAll'])->name('api.v1.notifications.read_all');
        Route::post('/notifications/{notificationDelivery}/read', [NoticeNotificationApiController::class, 'read'])->name('api.v1.notifications.read');

        // Correspondence API routes (mobile)
        Route::get('/correspondence/incoming', [CorrespondenceController::class, 'incoming'])->name('api.v1.correspondence.incoming');
        Route::get('/correspondence/outgoing', [CorrespondenceController::class, 'outgoing'])->name('api.v1.correspondence.outgoing');
        Route::get('/correspondence/org-units', [CorrespondenceController::class, 'orgUnits'])->middleware('permission:create_correspondence_management')->name('api.v1.correspondence.org_units');
        Route::get('/correspondence/{letter}', [CorrespondenceController::class, 'show'])->whereNumber('letter')->name('api.v1.correspondence.show');
        Route::post('/correspondence/store', [CorrespondenceController::class, 'store'])->middleware('permission:create_correspondence_management')->name('api.v1.correspondence.store');
        Route::post('/correspondence/{letter}/progress', [CorrespondenceController::class, 'progress'])->whereNumber('letter')->middleware('permission:update_correspondence_management')->name('api.v1.correspondence.progress');
        Route::post('/correspondence/{letter}/distribute', [CorrespondenceController::class, 'distribute'])->whereNumber('letter')->middleware('permission:update_correspondence_management')->name('api.v1.correspondence.distribute');
        Route::post('/correspondence/distribution/{distribution}/acknowledge', [CorrespondenceController::class, 'acknowledge'])->name('api.v1.correspondence.acknowledge');
        Route::post('/correspondence/distribution/{distribution}/feedback', [CorrespondenceController::class, 'feedback'])->name('api.v1.correspondence.feedback');
        Route::post('/correspondence/{letter}/feedback-parent', [CorrespondenceController::class, 'feedbackParent'])->whereNumber('letter')->name('api.v1.correspondence.feedback_parent');
        Route::get('/correspondence/{letter}/attachments/{index}/signed-url', [CorrespondenceController::class, 'attachmentSignedUrl'])
            ->whereNumber('letter')
            ->whereNumber('index')
            ->name('api.v1.correspondence.attachment_signed_url');
        Route::get('/correspondence/users/search', [CorrespondenceController::class, 'searchUsers'])->name('api.v1.correspondence.search_users');
        Route::get('/correspondence', [CorrespondenceController::class, 'index'])->name('api.v1.correspondence.dashboard');
    });
