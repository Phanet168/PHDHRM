<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceAccessRequestController;
use App\Http\Controllers\Api\ExternalSyncController;

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

Route::controller(ApiController::class)->group(function () {

    Route::get('/language', 'language')->name('language');
    Route::get('/webSetting', 'webSetting')->name('webSetting');

    Route::match(['get', 'post'], '/login', 'login')->name('api.legacy.login');
    Route::post('/password_recovery', 'password_recovery')->name('password_recovery');

    Route::get('/recovery_form/{token_id}','recoveryForm')->name('recovery_form');
    Route::post('/recovery_submit/{token_id}','recoverySubmit')->name('recovery_submit');

    Route::match(['get', 'post'], '/add_attendance', 'addAttendance')->name('api.add_attendance');

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
