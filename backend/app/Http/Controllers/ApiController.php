<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Setting\Entities\Application;
use App\Models\User;
use App\Models\Appsetting;
use App\Models\AttendanceScanLog;
use Modules\HumanResource\Entities\Employee;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Modules\HumanResource\Entities\Attendance;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Notice;
use Modules\HumanResource\Entities\Loan;
use Modules\HumanResource\Entities\ApplyLeave;
use Modules\HumanResource\Entities\WeekHoliday;
use Modules\HumanResource\Entities\SalaryGenerate;
use Modules\HumanResource\Entities\LeaveType;
use Modules\HumanResource\Entities\PointSettings;
use Modules\HumanResource\Services\AttendanceCaptureService;
use Modules\HumanResource\Services\QrAttendanceTokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ApiController extends Controller
{
    public function index()
    {

        $json['response'] = array(
            'status' => "Ok",
        );

        echo json_encode($json, JSON_UNESCAPED_UNICODE);
    }

    /*
    |--------------------------------------------------
    |
    |LANGUAGE LIST
    |-------------------------------------------------
    */
    public function language()
    {
        $json['response'] = array(
            'login'               => localize('login'),
            'add_attendance'      => localize('add_attendance'),
            'attendance_list'     => localize('attendance_list'),
            'attendance_history'  => localize('attendance_history'),
            'home'                => localize('home'),
            'give_attendance'     => localize('give_attendance'),
            'ledger_history'      => localize('ledger_history'),
            'request_leave'       => localize('request_leave'),
            'my_profile'          => localize('my_profile'),
            'notice_board'        => localize('notice'),
            'notice'              => localize('notices'),
            'salary_statement'    => localize('salary_statement'),
            'profile'             => localize('profile'),
            'working_hour'        => localize('working_hour'),
            'qr_attendance'       => localize('qr_attendance'),
            'loan_amount'         => localize('loan_amount'),
            'leave_remaining'     => localize('leave_remaining'),
            'total_attendance'    => localize('total_attendance'),
            'day_absent'          => localize('day_absent'),
            'day_present'         => localize('day_present'),
            'next'                => localize('next'),
            'previous'            => localize('previous'),
            'network_alert'       => localize('network_alert'),
            'select_date'         => localize('select_date'),
            'from'                => localize('from'),
            'to'                  => localize('to'),
            'search'              => localize('search'),
            'attendance_log'      => localize('attendance_log'),
            'date'                => localize('date'),
            'time'                => localize('times'),
            'in'                  => localize('in'),
            'out'                 => localize('out'),
            'work_hour'           => localize('work_hour'),
            'action'              => localize('action'),
            'load_more'           => localize('load_more'),
            'data_not_found'      => localize('data_not_found'),
            'view'                => localize('view'),
            'worked'              => localize('worked'),
            'wastage'             => localize('wastage'),
            'net_hours'           => localize('net_hour'),
            'sl'                  => localize('sl'),
            'status'              => localize('status'),
            'punch_time'          => localize('punch_time'),
            'loading'             => localize('loading'),
            'wrong_info_alert'    => localize('wrong_info_alert'),
            'from_to_date_alrt'   => localize('from_to_date_alrt'),
            'qr_scan'             => localize('qr_scan'),
            'stop_scan'           => localize('stop_scan'),
            'scan_again'          => localize('scan_again'),
            'confirm_attendance'  => localize('confirm_attendance'),
            'scan_alert'          => localize('scan_alert'),
            'attn_success_mgs'    => localize('attn_success_mgs'),
            'you_r_not_in_office' => localize('you_r_not_in_office'),
            'out_of_range'        => localize('out_of_range'),
            'debit'               => localize('debit'),
            'credit'              => localize('credit'),
            'balance'             => localize('balance'),
            'request_for_leave'   => localize('request_for_leave'),
            'leave_type'          => localize('leave_type'),
            'select_type'         => localize('select_type'),
            'leave_reason'        => localize('leave_reason'),
            'write_reason'        => localize('write_reason'),
            'send_request'        => localize('send_request'),
            'leave_his_status'    => localize('leave_his_status'),
            'amount'              => localize('amount'),
            'name'                => localize('name'),
            'salary_type'         => localize('sal_type'),
            'total_tax'           => localize('total_tax'),
            'basic_salary'        => localize('basic_salary'),
            'total_salary'        => localize('total_salary'),
            'bank_name'           => localize('bank_name'),
            'paid_by'             => localize('paid_by'),
            'employee'            => localize('employee'),
            'no'                  => localize('no'),
            'email'               => localize('email'),
            'phone'               => localize('phone'),
            'employee_id'         => localize('employee_id'),
            'employment_date'     => localize('employment_date'),
            'state'               => localize('state'),
            'company_name'        => localize('company_name'),
            'city'                => localize('city'),
            'zip'                 => localize('zip'),
            'present_address'     => localize('present_address'),
            'parmanent_address'   => localize('parmanent_address'),
            'education'           => localize('education'),
            'university_name'     => localize('university_name'),
            'notice_by'           => localize('notice_by'),
            'notice_date'         => localize('notice_date'),
            'notice_details'      => localize('notice_details'),
            'no_notice_to_show'   => localize('no_notice_to_show'),
            'welcome_msg'         => localize('welcome_msg'),
            'enter_your_email'    => localize('enter_your_email'),
            'enter_your_password' => localize('enter_your_password'),
            'cannot_remember_pass' => localize('cannot_remember_pass'),
            'forgot_password'     => localize('forgot_password'),
            'email_pass_cannot_empt'     => localize('email_pass_cannot_empt'),
            'email_format_was_not_right'     => localize('email_format_was_not_right'),
            'email_or_pass_not_matched'     => localize('email_or_pass_not_matched'),
            'reset_your_password' => localize('reset_your_password'),
            'reset'               => localize('reset'),
            'your_remember_password' => localize('your_remember_password'),
            'back_to_login'       => localize('back_to_login'),
            'email_fild_can_not_empty'     => localize('email_fild_can_not_empty'),
            'email_not_found'     => localize('email_not_found'),
            'successfully_send_email'     => localize('successfully_send_email'),
            'email_is_not_valid'     => localize('email_is_not_valid'),
            'sorry_email_not_sent'     => localize('sorry_email_not_sent'),
            'day_leave'             => localize('day_leave'),
            'search_work_details'             => localize('search_work_details'),
            'request_not_send'             => localize('request_not_send'),
            'leave_request_success'             => localize('leave_request_success'),
            'all_field_are_required'             => localize('all_field_are_required'),
            'plz_select_data_properly'             => localize('plz_select_data_properly'),
            'pending'             => localize('pending'),
            'approved'            => localize('approved'),
            'logout'              => localize('logout'),
            'paid'                => localize('paid'),
            'unpaid'              => localize('unpaid'),
            'salary_details'      => localize('salary_details'),
            'worked_days'        => localize('worked_days'),
        );

        echo json_encode($json, JSON_UNESCAPED_UNICODE);
    }

    /*
    |---------------------------------------------------
    |Web Settings Data 
    |---------------------------------------------------
    */

    public function webSetting()
    {

        $settings = Application::first();

        if (!empty($settings)) {

            $settings->logo = 'storage/' . $settings->logo;
            $settings->favicon = 'storage/' . $settings->favicon;
            $settings->language = 'language';

            $json['response'] = [
                'status'         => "Ok",
                'attendance_url' => route('api.add_attendance'),
                'base_url'       => url('/') . '/',
                'logo_url'       => app_setting()->logo,
                'settings'       => $settings,
            ];
        } else {

            $json['response'] = [
                'status'  => localize('error'),
                'message' => localize('settings_not_found')

            ];
        }

        echo $json_encode = json_encode($json, JSON_UNESCAPED_UNICODE);
    }

    /*-----------------------------------------------------
    |  ADD  ATTENDANCE 
    |
    |---------------------------------------------------
    */
    public function addAttendance(Request $request)
    {

        $ulatitude = $request->get('latitude');
        $ulongitude = $request->get('longitude');
        $employee_id = $request->get('employee_id');
        $time = $request->get('datetime');
        $scanTime = $this->parseScanTime($time);
        $userid = $request->get('user_id');
        $requestedMachineState = $request->get('machine_state');
        $requestedWorkplaceId = (int) $request->get('workplace_id', 0);
        $qrToken = (string) $request->get('qr_token', '');
        $qrPayloadRaw = $request->get('qr_payload');
        $employeeId = (int) $employee_id;
        $userId = is_numeric($userid) ? (int) $userid : null;

        $scanLogContext = [
            'employee_id' => $employeeId > 0 ? $employeeId : null,
            'user_id' => $userId,
            'workplace_id' => null,
            'latitude' => $ulatitude,
            'longitude' => $ulongitude,
            'scanned_at' => $scanTime,
            'qr_token' => $qrToken,
            'meta_payload' => [
                'source' => 'add_attendance',
            ],
        ];

        if (!is_numeric($ulatitude) || !is_numeric($ulongitude)) {
            $message = localize('location_required', 'Valid location is required');
            $scanLogId = $this->recordAttendanceScanEvent(array_merge($scanLogContext, [
                'status' => 'error',
                'error_code' => 'location_required',
                'message' => $message,
            ]));
            $json['response'] = [
                'status' => 'error',
                'error_code' => 'location_required',
                'scan_log_id' => $scanLogId,
                'message' => $message,
            ];
            echo json_encode($json, JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($employeeId <= 0) {
            $message = localize('employee_not_found', 'Employee not found');
            $scanLogId = $this->recordAttendanceScanEvent(array_merge($scanLogContext, [
                'status' => 'error',
                'error_code' => 'employee_not_found',
                'message' => $message,
            ]));
            $json['response'] = [
                'status' => 'error',
                'error_code' => 'employee_not_found',
                'scan_log_id' => $scanLogId,
                'message' => $message,
            ];
            echo json_encode($json, JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($qrToken === '' && is_string($qrPayloadRaw) && trim($qrPayloadRaw) !== '') {
            $decodedPayload = json_decode($qrPayloadRaw, true);
            if (is_array($decodedPayload) && isset($decodedPayload['qr_token'])) {
                $qrToken = (string) $decodedPayload['qr_token'];
                $scanLogContext['qr_token'] = $qrToken;
            }
        }

        $requireQrToken = (bool) config('humanresource.attendance.require_qr_token', false);
        $qrClaims = null;
        $resolvedWorkplaceId = null;

        if ($qrToken !== '') {
            try {
                $qrClaims = QrAttendanceTokenService::verify($qrToken);
                $resolvedWorkplaceId = (int) ($qrClaims['wid'] ?? 0);
            } catch (\Throwable $e) {
                $message = localize('invalid_or_expired_qr', 'Invalid or expired QR token');
                $scanLogId = $this->recordAttendanceScanEvent(array_merge($scanLogContext, [
                    'status' => 'error',
                    'error_code' => 'invalid_qr',
                    'message' => $message,
                ]));
                $json['response'] = [
                    'status' => 'error',
                    'error_code' => 'invalid_qr',
                    'scan_log_id' => $scanLogId,
                    'message' => $message,
                ];
                echo json_encode($json, JSON_UNESCAPED_UNICODE);
                return;
            }
        } elseif ($requireQrToken) {
            $message = localize('qr_token_required', 'QR token is required');
            $scanLogId = $this->recordAttendanceScanEvent(array_merge($scanLogContext, [
                'status' => 'error',
                'error_code' => 'qr_token_required',
                'message' => $message,
            ]));
            $json['response'] = [
                'status' => 'error',
                'error_code' => 'qr_token_required',
                'scan_log_id' => $scanLogId,
                'message' => $message,
            ];
            echo json_encode($json, JSON_UNESCAPED_UNICODE);
            return;
        }

        $employee = Employee::query()
            ->select('id', 'department_id', 'sub_department_id')
            ->find($employeeId);
        if (!$employee) {
            $message = localize('employee_not_found', 'Employee not found');
            $scanLogId = $this->recordAttendanceScanEvent(array_merge($scanLogContext, [
                'status' => 'error',
                'error_code' => 'employee_not_found',
                'message' => $message,
            ]));
            $json['response'] = [
                'status' => 'error',
                'error_code' => 'employee_not_found',
                'scan_log_id' => $scanLogId,
                'message' => $message,
            ];
            echo json_encode($json, JSON_UNESCAPED_UNICODE);
            return;
        }

        if (empty($resolvedWorkplaceId) || $resolvedWorkplaceId <= 0) {
            if ($requestedWorkplaceId > 0) {
                $resolvedWorkplaceId = $requestedWorkplaceId;
            } else {
                $resolvedWorkplaceId = (int) ($employee->sub_department_id ?: $employee->department_id ?: 0);
            }
        }
        $scanLogContext['workplace_id'] = $resolvedWorkplaceId > 0 ? (int) $resolvedWorkplaceId : null;

        $userInfo = User::query()->find($userid);
        $user_data = null;
        if ($userInfo && $userInfo->email) {
            $user_data = $this->userData($userInfo->email);
            if ($user_data) {
                $user_data->firstname = $user_data->first_name;
                $user_data->lastname = $user_data->last_name;
            }
        }

        $settingdata = Appsetting::first();
        if (!$settingdata) {
            $message = localize('settings_not_found', 'Settings not found');
            $scanLogId = $this->recordAttendanceScanEvent(array_merge($scanLogContext, [
                'status' => 'error',
                'error_code' => 'settings_not_found',
                'message' => $message,
            ]));
            $json['response'] = [
                'status' => 'error',
                'error_code' => 'settings_not_found',
                'scan_log_id' => $scanLogId,
                'message' => $message,
            ];
            echo json_encode($json, JSON_UNESCAPED_UNICODE);
            return;
        }

        $acceptableRange = (float) ($settingdata->acceptablerange ?? 0);
        if ($acceptableRange <= 0) {
            $message = localize('invalid_attendance_range', 'Attendance range is not configured');
            $scanLogId = $this->recordAttendanceScanEvent(array_merge($scanLogContext, [
                'status' => 'error',
                'error_code' => 'invalid_attendance_range',
                'message' => $message,
            ]));
            $json['response'] = [
                'status' => 'error',
                'error_code' => 'invalid_attendance_range',
                'scan_log_id' => $scanLogId,
                'message' => $message,
            ];
            echo json_encode($json, JSON_UNESCAPED_UNICODE);
            return;
        }

        $geofenceLatitude = is_numeric($settingdata->latitude) ? (float) $settingdata->latitude : null;
        $geofenceLongitude = is_numeric($settingdata->longitude) ? (float) $settingdata->longitude : null;
        $geofenceSource = 'global';
        $workplace = null;

        if ($resolvedWorkplaceId > 0) {
            $workplace = Department::withoutGlobalScopes()
                ->select('id', 'department_name', 'latitude', 'longitude')
                ->find((int) $resolvedWorkplaceId);

            if ($workplace && is_numeric($workplace->latitude) && is_numeric($workplace->longitude)) {
                $geofenceLatitude = (float) $workplace->latitude;
                $geofenceLongitude = (float) $workplace->longitude;
                $geofenceSource = 'workplace';
            }
        }

        if ($geofenceLatitude === null || $geofenceLongitude === null) {
            $message = localize('geofence_not_configured', 'Geofence coordinates are not configured');
            $scanLogId = $this->recordAttendanceScanEvent(array_merge($scanLogContext, [
                'status' => 'error',
                'error_code' => 'geofence_not_configured',
                'message' => $message,
                'acceptable_range_meters' => $acceptableRange,
            ]));
            $json['response'] = [
                'status' => 'error',
                'error_code' => 'geofence_not_configured',
                'scan_log_id' => $scanLogId,
                'message' => $message,
            ];
            echo json_encode($json, JSON_UNESCAPED_UNICODE);
            return;
        }

        $distanceMetre = $this->calculateDistanceMeters(
            $geofenceLatitude,
            $geofenceLongitude,
            (float) $ulatitude,
            (float) $ulongitude
        );
        $distance = number_format($distanceMetre, 1, '.', '');

        if ($distanceMetre <= $acceptableRange) {
            
            $captured = AttendanceCaptureService::capture([
                'employee_id' => $employeeId,
                'time' => $scanTime,
                'machine_state' => is_numeric($requestedMachineState) ? (int) $requestedMachineState : null,
                'attendance_source' => 'api_qr',
                'workplace_id' => $resolvedWorkplaceId > 0 ? $resolvedWorkplaceId : null,
                'scan_latitude' => $ulatitude,
                'scan_longitude' => $ulongitude,
                'source_reference' => 'api_user:' . $userid . ($qrClaims ? '|qr:' . ($qrClaims['jti'] ?? '') : ''),
            ]);

            if ($captured) {
                $machineState = (int) ($captured->machine_state ?? 0);
                $punchType = $this->machineStateLabel($machineState);
                $scanLogId = $this->recordAttendanceScanEvent(array_merge($scanLogContext, [
                    'status' => 'success',
                    'error_code' => null,
                    'message' => 'Successfully Saved',
                    'range_meters' => $distanceMetre,
                    'acceptable_range_meters' => $acceptableRange,
                    'geofence_source' => $geofenceSource,
                    'workplace_id' => $resolvedWorkplaceId > 0 ? $resolvedWorkplaceId : null,
                    'meta_payload' => [
                        'source' => 'add_attendance',
                        'machine_state' => $machineState,
                        'punch_type' => $punchType,
                    ],
                ]));
                $json['response'] = [
                    'status'     => 'ok',
                    'range'     => $distance,
                    'acceptable_range' => number_format($acceptableRange, 1, '.', ''),
                    'workplace_id' => $resolvedWorkplaceId > 0 ? $resolvedWorkplaceId : null,
                    'workplace_name' => $workplace?->department_name,
                    'geofence_source' => $geofenceSource,
                    'machine_state' => $machineState,
                    'punch_type' => $punchType,
                    'scan_log_id' => $scanLogId,
                    'message'    => 'Successfully Saved',

                ];

                $icon = '';
                $fields3 = array(
                    'to' => $user_data?->token_id,
                    'data' => array(
                        'title' => "Attendance",
                        'body' => "Dear " . ($user_data?->firstname ?? '') . ' ' . ($user_data?->lastname ?? '') . " Your Attendance Successfully Saved",
                        'image' => $icon,
                        'media_type' => "image",
                        "action"=> "2",
                    ),
                    'notification' => array(
                        'sound' => "default",
                        'title' => "Attendance",
                        'body' => "Dear " . ($user_data?->firstname ?? '') . ' ' . ($user_data?->lastname ?? '') . " Your Attendance Successfully Saved",
                        'image' => $icon,
                    )
                );

                if (!empty($user_data?->token_id) && !empty($settingdata->googleapi_authkey)) {
                    $post_data3 = json_encode($fields3);
                    $url = "https://fcm.googleapis.com/fcm/send";
                    $ch3  = curl_init($url);
                    curl_setopt($ch3, CURLOPT_FAILONERROR, true);
                    curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, 0);
                    curl_setopt($ch3, CURLOPT_CONNECTTIMEOUT, 3);
                    curl_setopt($ch3, CURLOPT_TIMEOUT, 5);
                    curl_setopt($ch3, CURLOPT_POSTFIELDS, $post_data3);
                    curl_setopt($ch3, CURLOPT_HTTPHEADER, array(
                        $settingdata->googleapi_authkey,
                        'Content-Type: application/json'
                    ));
                    curl_exec($ch3);
                    curl_close($ch3);
                }
            } else {
                $message = localize('please_try_again');
                $scanLogId = $this->recordAttendanceScanEvent(array_merge($scanLogContext, [
                    'status' => 'error',
                    'error_code' => 'capture_failed',
                    'message' => $message,
                    'range_meters' => $distanceMetre,
                    'acceptable_range_meters' => $acceptableRange,
                    'geofence_source' => $geofenceSource,
                    'workplace_id' => $resolvedWorkplaceId > 0 ? $resolvedWorkplaceId : null,
                ]));
                $json['response'] = [
                    'status'     => 'error',
                    'error_code' => 'capture_failed',
                    'range'      => $distance,
                    'acceptable_range' => number_format($acceptableRange, 1, '.', ''),
                    'workplace_id' => $resolvedWorkplaceId > 0 ? $resolvedWorkplaceId : null,
                    'workplace_name' => $workplace?->department_name,
                    'geofence_source' => $geofenceSource,
                    'scan_log_id' => $scanLogId,
                    'message'    =>  $message,

                ];
            }
        } else {
            $message = localize('out_of_range');
            $scanLogId = $this->recordAttendanceScanEvent(array_merge($scanLogContext, [
                'status' => 'error',
                'error_code' => 'out_of_range',
                'message' => $message,
                'range_meters' => $distanceMetre,
                'acceptable_range_meters' => $acceptableRange,
                'geofence_source' => $geofenceSource,
                'workplace_id' => $resolvedWorkplaceId > 0 ? $resolvedWorkplaceId : null,
            ]));
            $json['response'] = [
                'status'     => 'error',
                'error_code' => 'out_of_range',
                'range'    => $distance,
                'acceptable_range' => number_format($acceptableRange, 1, '.', ''),
                'workplace_id' => $resolvedWorkplaceId > 0 ? $resolvedWorkplaceId : null,
                'workplace_name' => $workplace?->department_name,
                'geofence_source' => $geofenceSource,
                'scan_log_id' => $scanLogId,
                'message'    => $message,

            ];
        }

        echo json_encode($json, JSON_UNESCAPED_UNICODE);

    }

    protected function calculateDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusMeters = 6371000.0;

        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lonDelta / 2) ** 2;
        $a = min(1.0, max(0.0, $a));
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusMeters * $c;
    }

    public function attendanceScanLog(Request $request)
    {
        $employeeId = (int) $request->get('employee_id');
        $userId = $request->get('user_id');
        $workplaceId = (int) $request->get('workplace_id');
        $status = trim((string) $request->get('status', 'client_error'));
        $errorCode = trim((string) $request->get('error_code', 'client_error'));
        $message = trim((string) $request->get('message', ''));
        $rangeMeters = $request->get('range');
        $acceptableRange = $request->get('acceptable_range');
        $latitude = $request->get('latitude');
        $longitude = $request->get('longitude');
        $qrToken = (string) $request->get('qr_token', '');
        $geofenceSource = $request->get('geofence_source');
        $scannedAt = $this->parseScanTime($request->get('datetime'));

        $logId = $this->recordAttendanceScanEvent([
            'employee_id' => $employeeId > 0 ? $employeeId : null,
            'user_id' => is_numeric($userId) ? (int) $userId : null,
            'workplace_id' => $workplaceId > 0 ? $workplaceId : null,
            'status' => $status !== '' ? $status : 'client_error',
            'error_code' => $errorCode !== '' ? $errorCode : null,
            'message' => $message !== '' ? $message : null,
            'range_meters' => $rangeMeters,
            'acceptable_range_meters' => $acceptableRange,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'qr_token' => $qrToken,
            'geofence_source' => is_string($geofenceSource) ? $geofenceSource : null,
            'scanned_at' => $scannedAt,
            'meta_payload' => [
                'source' => 'mobile_client_log',
                'client_payload' => $request->except(['qr_token']),
            ],
        ]);

        $json['response'] = [
            'status' => 'ok',
            'log_id' => $logId,
            'message' => localize('successfully_saved', 'Successfully saved'),
        ];

        echo json_encode($json, JSON_UNESCAPED_UNICODE);
    }

    public function attendanceScanLogs(Request $request)
    {
        $employeeId = (int) $request->get('employee_id');
        $status = trim((string) $request->get('status', ''));
        $errorCode = trim((string) $request->get('error_code', ''));
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $limit = (int) $request->get('limit', 50);
        $limit = max(1, min(200, $limit));

        $query = AttendanceScanLog::query()->orderByDesc('id');

        if ($employeeId > 0) {
            $query->where('employee_id', $employeeId);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($errorCode !== '') {
            $query->where('error_code', $errorCode);
        }

        if (is_string($fromDate) && trim($fromDate) !== '') {
            $query->whereDate('scanned_at', '>=', Carbon::parse($fromDate)->format('Y-m-d'));
        }

        if (is_string($toDate) && trim($toDate) !== '') {
            $query->whereDate('scanned_at', '<=', Carbon::parse($toDate)->format('Y-m-d'));
        }

        $summaryBaseQuery = clone $query;

        $logs = $query
            ->limit($limit)
            ->get([
                'id',
                'employee_id',
                'user_id',
                'workplace_id',
                'status',
                'error_code',
                'message',
                'range_meters',
                'acceptable_range_meters',
                'geofence_source',
                'latitude',
                'longitude',
                'request_ip',
                'scanned_at',
                'created_at',
            ]);

        $statusSummary = (clone $summaryBaseQuery)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(function ($value) {
                return (int) $value;
            })
            ->toArray();

        $errorSummary = (clone $summaryBaseQuery)
            ->select('error_code', DB::raw('COUNT(*) as total'))
            ->whereNotNull('error_code')
            ->where('error_code', '!=', '')
            ->groupBy('error_code')
            ->pluck('total', 'error_code')
            ->map(function ($value) {
                return (int) $value;
            })
            ->toArray();

        $json['response'] = [
            'status' => 'ok',
            'total' => $logs->count(),
            'logs' => $logs,
            'summary' => [
                'status' => $statusSummary,
                'error_code' => $errorSummary,
            ],
        ];

        echo json_encode($json, JSON_UNESCAPED_UNICODE);
    }

    protected function recordAttendanceScanEvent(array $payload): ?int
    {
        try {
            $entry = AttendanceScanLog::query()->create([
                'employee_id' => $this->nullableInt($payload['employee_id'] ?? null),
                'user_id' => $this->nullableInt($payload['user_id'] ?? null),
                'workplace_id' => $this->nullableInt($payload['workplace_id'] ?? null),
                'status' => (string) ($payload['status'] ?? 'error'),
                'error_code' => $this->nullableString($payload['error_code'] ?? null),
                'message' => $this->nullableString($payload['message'] ?? null),
                'range_meters' => $this->nullableFloat($payload['range_meters'] ?? null),
                'acceptable_range_meters' => $this->nullableFloat($payload['acceptable_range_meters'] ?? null),
                'geofence_source' => $this->nullableString($payload['geofence_source'] ?? null),
                'latitude' => $this->nullableFloat($payload['latitude'] ?? null),
                'longitude' => $this->nullableFloat($payload['longitude'] ?? null),
                'request_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'qr_token_hash' => $this->tokenHash($payload['qr_token'] ?? null),
                'meta_payload' => is_array($payload['meta_payload'] ?? null) ? $payload['meta_payload'] : null,
                'scanned_at' => $this->parseScanTime($payload['scanned_at'] ?? null),
            ]);

            return (int) $entry->id;
        } catch (\Throwable $e) {
            Log::warning('attendance_scan_log_failed', [
                'message' => $e->getMessage(),
            ]);
        }

        return null;
    }

    protected function parseScanTime($value): string
    {
        try {
            if (is_string($value) && trim($value) !== '') {
                return Carbon::parse($value)->format('Y-m-d H:i:s');
            }
        } catch (\Throwable $e) {
            // Fallback to now if payload date is invalid.
        }

        return now()->format('Y-m-d H:i:s');
    }

    protected function machineStateLabel(int $machineState): string
    {
        if ($machineState === 1) {
            return 'IN';
        }

        if ($machineState === 2) {
            return 'OUT';
        }

        return 'UNKNOWN';
    }

    protected function nullableInt($value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    protected function nullableFloat($value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    protected function nullableString($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $text = trim($value);
        return $text === '' ? null : $text;
    }

    protected function tokenHash($token): ?string
    {
        if (!is_string($token)) {
            return null;
        }

        $token = trim($token);
        if ($token === '') {
            return null;
        }

        return hash('sha256', $token);
    }

    /*
    |---------------------------------------------------
    |    Login info
    |---------------------------------------------------
    */

    public function login(Request $request)
    {

        $email = $request->get('email');
        $password =  $request->get('password');
        $token   = $request->get('token_id');

        $userInfo = $this->userData($email);
        if ($userInfo && $userInfo->profile_pic != null) {
            $userInfo->profile_pic = 'storage/' . $userInfo->profile_pic;
        }

        if (empty($email) || empty($password)) {
            $json['response'] = [
                'status'      => localize('error'),
                'type'        => 'required_field',
                'message'     => 'required_field',
                'permission' => 'read'
            ];
        } else {

            $data['user'] = (object) $userData =  [
                'email'      => $email,
                'password'   => $password
            ];

            $user = $this->checkUser($userData);
            $img = $userInfo?->profile_pic;

            if ($user && $userInfo) {
                $token_data = array(
                    'token_id' => $token,
                );
                // Find the user by email and update the token data
                User::where('email', $email)->update($token_data);

                $sData = array(
                    'user_id'     => $userInfo->id,
                    'tokendata'   => $token,
                    'password'    => $password,
                    'profile_pic' => (!empty($img) ? url('/') . '/' . $img : ""),
                    'userdata'    => $userInfo,
                );

                $json['response'] = [
                    'status'  => 'ok',
                    'user_data'    => $sData,
                    'message' => localize('successfully_login'),
                ];
            } else {
                $json['response'] = [
                    'status'  => localize('error'),
                    'message' => localize('no_data_found'),

                ];
            }
        }

        echo json_encode($json, JSON_UNESCAPED_UNICODE);
    }

    /*-----------------------------------------------------
    |  CHANGE PASSWORD 
    |
    |---------------------------------------------------
    */

    public function password_recovery(Request $request)
    {

        try {
            // Validate the request data
            $validatedData = $request->validate([
                'email' => 'required|email|max:100',
            ]);

            $userData = array(
                'email' => $request->input('email')
            );

            $user = User::select('*')->where('email', $userData['email'])->first();

            $ptoken = date('ymdhis');

            if ($user) {

                $email = $user->email;

                $precdat = array(
                    'email'      => $email,
                    'password_reset_token' => $ptoken,
                );

                // Find the user by email and update the token data
                User::where('email', $email)->update($precdat);
                $send_email = '';
                if (!empty($email)) {
                    $send_email = $this->setmail($email, $ptoken);
                }

                if ($send_email) {
                    $json['response'] = [
                        'status'     => 'ok',
                        'message'    => localize('check_Your_email'),

                    ];
                } else {
                    $json['response'] = [
                        'status'     => 'error',
                        'message'    => localize('sorry_email_not_sent'),

                    ];
                }
            } else {
                $json['response'] = [
                    'status'     => 'error',
                    'message'    => localize('email_not_found'),

                ];
            }

            echo json_encode($json, JSON_UNESCAPED_UNICODE);
            exit;

            // If validation passes, continue with your logic
        } catch (ValidationException $e) {
            // Validation failed, retrieve the validation errors
            $errors = $e->validator->errors();

            $json['response'] = [
                'status'     => 'error',
                'message'    => 'Email Is Not Valid',

            ];
            echo json_encode($json, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /*-----------------------------------------------------
    |  SEND MAIL TO USER
    |
    |---------------------------------------------------
    */
    public function setmail($email, $ptoken)
    {
        $msg = "Click on this url for change your password :" . url('/') . '/' . 'api/recovery_form/' . $ptoken;

        // use wordwrap() if lines are longer than 70 characters
        return $msg = wordwrap($msg, 100);

        // send email
        mail($email, "Password Recovery", $msg);
    }

    public function recoveryForm($token_id)
    {

        $burl = url('/') . '/';

        $tokeninfo = $this->token_matching($token_id);
        if ($tokeninfo) {

            $token = $token_id;
            $title = localize('recovery_form');

            return view(
                'recovery_form',
                compact(
                    'token',
                    'title',
                )
            );
        } else {
            return redirect()->route('login');
        }
    }

    /*-----------------------------------------------------
    |  RECOVER PASSWORD
    |
    |---------------------------------------------------
    */
    public function recoverySubmit(Request $request, $token_id)
    {

        try {

            // Validate the request data
            $request->validate([
                'password' => 'required|min:8', // New password validation rules
            ]);

            $token = $request->input('token', true);
            $newpassword = $request->input('password', true);

            // Find the user by the provided field (e.g., username)
            $user = User::where('password_reset_token', $token)->first();
            if ($user) {

                // Update the user's password
                $user->password = Hash::make($request->password);
                $user->save();

                // Redirect the user or return a response
                return redirect()->route('login')->with('success', 'Password updated successfully');
            } else {
                return redirect()->route('recovery_form', $token_id)->with('fail', 'User not found');
            }
        } catch (ValidationException $e) {
            // Validation failed, retrieve the validation errors
            $errors = $e->validator->errors();

            return redirect()->route('recovery_form', $token_id)->with('fail', 'Password must be at last 8 characters');
        }
    }

    public function token_matching($token_id)
    {
        return User::select('*')->where('password_reset_token', $token_id)->first();
    }


    /*-----------------------------------------------------
    |   ATTENDANCE HISTORY FOR ALL ATTENDANCE DATA
    |
    |------------------------------------------------
    */

    public function attendanceHistory(Request $request)
    {

        $start = $request->get('start');
        $employee_id = $request->get('employee_id');

        $total_attn = $this->count_att_history($employee_id);
        if (empty($start)) {
            $attendance = $this->attendance_history($employee_id);  
        } else {
            $attendance = $this->attendance_historylimit($employee_id,$start);
        }
        
        $add_data = [];
        foreach($attendance as $myattendance){

            $dt = Carbon::parse($myattendance['time']);
            $date = $dt->format('Y-m-d');
            $date_y = $dt->year;
            $date_m = $dt->month;
            $date_d = $dt->day;

            $add_data[] =   DB::table('attendances as a')
            ->select('a.*', DB::raw('CONCAT_WS(" ", b.first_name, b.last_name) AS employee_name'), DB::raw('DATE(time) as date'), DB::raw('TIME(time) as time'),
                DB::raw('(SELECT TIMEDIFF(MAX(time), MIN(time)) FROM attendances WHERE employee_id = "' . $employee_id . '" AND DATE(time) = "' . $date . '") as totalhours'))
            ->leftJoin('employees as b', 'b.id', '=', 'a.employee_id')
            ->where('a.employee_id', $employee_id)
            ->whereRaw("YEAR(a.time) = ?", [$date_y])
            ->whereRaw("MONTH(a.time) = ?", [$date_m])
            ->whereRaw("DAY(a.time) = ?", [$date_d])
            ->orderBy('a.id', 'ASC')
            ->groupBy('a.id')
            ->get();
        }

        // dd($add_data);
        
        if(!empty($attendance)){
            $json['response'] = [
                'status'      => 'ok',
                'length'      => "$total_attn",
                'historydata' => $add_data,
                'message'     => localize('found_some_data'),

            ];

        }else{
            $json['response'] = [
                'status'     => 'error',
                'length'      => "",
                'historydata' => [],
                'message'    => localize('no_record_found'),

            ]; 
        }
        echo json_encode($json,JSON_UNESCAPED_UNICODE);
    }

    public function attendance_history($employeeId)
    {

        return DB::table('attendances')
            ->select(DB::raw('*, DATE(time) as mydate, TIMEDIFF(MAX(time), MIN(time)) as totalhours'))
            ->where('employee_id', $employeeId)
            ->groupBy('mydate')
            ->orderByDesc('time')
            ->get()
            ->toArray();
    }

    public function count_att_history($employeeId){

        return Attendance::selectRaw('*, DATE(time) as mydate, TIMEDIFF(MAX(time), MIN(time)) as totalhours')
            ->where('employee_id', $employeeId)
            ->groupBy(DB::raw('mydate'))
            ->orderBy('time', 'desc')
            ->get()
            ->count();
     }

    public function attendance_historylimit($employeeId, $limit)
    {

        return Attendance::selectRaw('*, DATE(time) as mydate, TIMEDIFF(MAX(time), MIN(time)) as totalhours')
            ->where('employee_id', $employeeId)
            ->groupBy(DB::raw('mydate'))
            ->orderBy('time', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /*-----------------------------------------------------
    |  DATE WISE ATTENDANCE
    |
    |------------------------------------------------
    */

    public function attendanceDatewise(Request $request){
        
        $employee_id = $request->get('employee_id');
        $fromdate = $request->get('from_date');
        $todate = $request->get('to_date');
        $attendance = $this->attendance_history_datewise($employee_id,$fromdate,$todate);
        $policy = $this->attendancePolicy();


        if(!empty($attendance)){
            $json['response'] = [
                'status'      => 'ok',
                'historydata' =>  $attendance,
                'summary'     => $this->attendanceSummary($attendance),
                'policy'      => [
                    'attendance_start' => $policy['attendance_start'],
                    'attendance_end' => $policy['attendance_end'],
                    'late_grace_minutes' => $policy['late_grace_minutes'],
                    'early_leave_grace_minutes' => $policy['early_leave_grace_minutes'],
                ],
                'message'     => localize('found_some_data'),
                
            ];
            
        }else{
            $json['response'] = [
                'status'     => 'error',
                'message'    => localize('no_record_found'),
                
            ]; 
        }

        echo json_encode($json,JSON_UNESCAPED_UNICODE);
        
    }

    /*-----------------------------------------------------
    | Total Hours of current month
    |
    |------------------------------------------------
    */

    public function currentMonthTotalHours(Request $request){
    
    
        $query_date = date('Y-m-d');
        $employee_id = $request->get('employee_id');
        $fromdate = date('Y-m-01', strtotime($query_date));
        $todate = date('Y-m-t', strtotime($query_date));
        $allhours = $this->attendance_history_datewise($employee_id,$fromdate,$todate);

        $totalhour=[];
        $idx=1;
        
        foreach($allhours as $hours){

            $hou = 0;
            $min = 0;
            $sec = 0;
        
            $split = explode(":", @$hours['nethours']); 
                        $hou += @$split[0];
                        $min += @$split[1];
                        $sec += @$split[2];

                $seconds = $sec % 60;
                $minutes = $sec / 60;
                $minutes = (integer)$minutes;
                $minutes += $min;
                $hours = $minutes / 60;
                $minutes = $minutes % 60;
                $hours = (integer)$hours;
                $hours += $hou % 24;
        
            $totalnethours = $hours.":".$minutes.":".$seconds;
                $totalhour[$idx] = $totalnethours;
                
                $idx++;
        }
    
        $seconds = 0;
        foreach($totalhour as $t)
        {
            $timeArr = array_reverse(explode(":", $t));
            foreach ($timeArr as $key => $value)
            {
                if ($key > 2) break;
                $seconds += pow(60, $key) * $value;
            }
        }

        $hours = floor($seconds / 3600);
        $mins = floor(($seconds - ($hours*3600)) / 60);
        $secs = floor($seconds % 60);

        $ntotalhours  =  $hours.':'.$mins.':'.$secs;
        
        if(!empty($allhours)){
            $json['response'] = [
                'status'      => 'ok',
                'totalhours'  =>  $ntotalhours,
                'message'     => localize('found_some_data'),
            
            ];
            
        }else{
            $json['response'] = [
                'status'     => 'error',
                'message'    => localize('no_record_found'),
            
            ]; 
        }

        echo json_encode($json,JSON_UNESCAPED_UNICODE);
        
    }

    /*-----------------------------------------------------
    |  NOTICE BOARD
    |
    |------------------------------------------------
    */

    public function noticeInfo(Request $request){
        
        $start=$request->get('start');
        $totlanotice = $this->count_notice();
        if(empty($start)){
            $notice = $this->notice_boardall();
            
        }else{
            $notice = $this->notice_board($start);
        }

        foreach($notice as $key => $notice_r){
            $notice[$key]['notice_attachment'] = 'storage/' . $notice_r['notice_attachment'];
        }

        if(!empty($notice)){
            $json['response'] = [
                'status'      => 'ok',
                'length'      => $totlanotice,
                'historydata' =>  $notice,
                'message'     => localize('found_some_data'),
            
            ];
            
        }else{
            $json['response'] = [
                'status'     => 'error',
                'message'    =>  localize('no_record_found'),
            
            ]; 
        }
        echo json_encode($json,JSON_UNESCAPED_UNICODE);
        
    }

    /*-----------------------------------------------------
    | Loan Amount Remaining info
    |
    |------------------------------------------------
    */

    public function loanAmount(Request $request){
        
        
        $employee_id = $request->get('employee_id');
        $totaldue = $this->total_loan_amount($employee_id);
        
        if(!empty($totaldue)){

            $json['response'] = [
                'status'      => 'ok',
                'totalamount' =>  "$totaldue",
                'message'     => localize('found_some_data'),
                
            ];
            
        }else{
            $json['response'] = [
                'status'     => 'error',
                'totalamount' =>  "",
                'message'     => localize('no_record_found'),
                
            ]; 
        }
        echo json_encode($json,JSON_UNESCAPED_UNICODE);
        
    }

    public function total_loan_amount($id){

        $totalpayble = null;

        // Raw SQL query with a binding
        $loanreceive = "SELECT *, SUM(repayment_amount) as totalreceive FROM `loans` WHERE `employee_id` = ? AND (`released_amount` IS NULL OR `repayment_amount` > COALESCE(`released_amount`, 0))";

        // Execute the query with the binding
        $totalpayble = DB::select($loanreceive, [$id]);

        // Check if the result is not empty and get the first row
        if (!empty($totalpayble)) {
            $totalpayble = $totalpayble[0];
        }

        $due = (!empty($totalpayble)?$totalpayble->repayment_amount:0) - (!empty($totalpayble)?$totalpayble->released_amount:0);
        return $due;

    }

    /*-----------------------------------------------------
    | Dashboard Graph info
    |
    |------------------------------------------------
    */

    public function graphInfo(Request $request){
        
        $query_date = date('Y-m-d');
        $employee_id = $request->get('employee_id');
        $fromdate = date('Y-m-01', strtotime($query_date));
        $todate = date('Y-m-d', strtotime($query_date));

        $alldays = $this->attendance_totalday_currentmonth($employee_id,$fromdate,$todate);
        $takenleave = $this->takenleave($employee_id);
        $weekend    = $this->weekends();
        $totaldaycurrentdate = $this->totaldayofcurrentstage();
        
        $absentdays = $totaldaycurrentdate - ($alldays + (!empty($takenleave)?$takenleave:0) + (!empty($weekend)?$weekend:0));
        if($absentdays > 0){
            $absentdays = $absentdays; 
        }else{
            $absentdays = '';
        }
        
        if(!empty($alldays)){
            $json['response'] = [
                'status'         => 'ok',
                'totalpresent'   => "$alldays",
                'takenleave'     => (!empty($takenleave)?$takenleave:''),
                'weekendholiday' => "$weekend",
                'Monthname'      => date('F'),
                'date'           => date('Y-m-d'),
                'absent'         => "$absentdays",
                'message'        => localize('found_some_data'),
                
            ];
            
        }else{
            $json['response'] = [
                'status'          => 'ok',
                'totalpresent'   => '',
                'takenleave'     => '',
                'weekendholiday' => '',
                'Monthname'      => date('F'),
                'date'           => date('Y-m-d'),
                'absent'         => '',
                
            ]; 
        }

        echo json_encode($json,JSON_UNESCAPED_UNICODE);
        
    }


    /*-----------------------------------------------------
    | Salary info
    |
    |-----------------------------------------------------
    */

    public function salaryInfo(Request $request){
        
        $start=$request->get('start');    
        $employee_id = $request->get('employee_id');

        $total = $this->count_payroll_salaryinfo($employee_id);
        
        if(empty($start)){
            $salaryinfo = $this->payroll_salaryinfo($employee_id);

        }else{
            $salaryinfo = $this->payroll_salaryinfolimit($employee_id,$start);    
        }
      
        if(!empty($salaryinfo)){
            $json['response'] = [
                    'status'        => 'ok',
                    'lenght'        => "$total",
                    'salary_info'       => $salaryinfo,
                    'message'           => localize('found_some_data'),
                           
                ];
           
        }else{
            $json['response'] = [
                    'status'                 => 'error',
                    'lenght'                 => "",
                    'salary_info'            => [],
                    'message'                =>  localize('no_record_found'),
                           
                ]; 
        }

        echo json_encode($json,JSON_UNESCAPED_UNICODE);
        
    }

    /*
    -------------------------------------------------------------------
    |
    |LEAVE TYPE LIST
    |
    --------------------------------------------------------------------
    */
    public function leaveTypeList(){
        $typelist = $this->type_list();

        foreach($typelist as $key => $value){
            $typelist[$key]['leave_type_id'] = $value['id'];
        }
        
        if(!empty($typelist)){
            $json['response'] = [
                        'status'        => 'ok',
                        'type_list'     => $typelist,
                        'message'       => localize('no_record_found'),
                    
                    ];
        }else{
                $json['response'] = [
                        'status'     => 'error',
                        'message'    => localize('found_some_data'),
                    
                    ]; 
        }
        
        echo json_encode($json,JSON_UNESCAPED_UNICODE);
    }

    /*-----------------------------------------------------
    | LEAVE APPLICATION 
    |
    |------------------------------------------------
    */

    public function leaveApplication(Request $request){

        $employee_id = $request->get('employee_id'); 
        $from_date   = $request->get('from_date');
        $to_date     = $request->get('to_date');

        // Create Carbon instances for the start and end dates
        $startdate = Carbon::createFromFormat('Y-m-d', $from_date);
        $enddate = Carbon::createFromFormat('Y-m-d', $to_date);

        $employee_info = Employee::where('id', $employee_id)->first();

        // Calculate the difference in days
        $apply_day = $startdate->diffInDays($enddate);

        $leave_type  = $request->get('type_id',true);
        $reason      = $request->get('reason',true);

        $lv = ApplyLeave::where('employee_id', $employee_id)
            ->where('leave_type_id', $leave_type)
            ->sum('total_approved_day');

        // To get the result as an object similar to `row()`
        $employee_leave = (object) ['lv' => $lv];

        $userid  = $employee_info->user_id;
        $userInfo = User::select('*')->where('id', $userid)->first();
        $user_data = $this->userData($userInfo->email);
        $user_data->firstname = $user_data->first_name;
        $user_data->lastname = $user_data->last_name;
        
        $settingdata = Appsetting::first();
        
        $totalleave = LeaveType::select('leave_days')
            ->where('id', $leave_type)
            ->first();
        if($employee_leave->lv < $totalleave->leave_days){
        
            $data = array(
                'uuid'                   => (string) Str::uuid(),
                'employee_id'            => $employee_id,
                'leave_type_id'          => $leave_type,
                'leave_apply_start_date' => $from_date,
                'leave_apply_end_date'   => $to_date,
                'total_apply_day'        => $apply_day+1,
                'reason'                 => $reason,
                'leave_apply_date'       => date('Y-m-d'),
            );
        
            if($resp = $this->insert_leave_application($data)){

                $json['response'] = [
                    'status'        => 'ok',
                    'message'       => 'Successfully Saved',
                    
                ];
    
                $icon='';
                $fields3 = array(
                    'to'=> $user_data->token_id,
                    'data'=>array(
                        'title'=>"Leave Application",
                        'body'=>"Dear ".$user_data->firstname.' '.$user_data->lastname." Your Leave Request Successfull",
                        'image'=>$icon,
                        'media_type'=>"image",
                        "action"=> "3",
                    ),
                    'notification'=>array(
                        'sound'=>"default",
                        'title'=>"Leave Application",
                        'body'=>"Dear ".$user_data->firstname.' '.$user_data->lastname." Your Leave Request Successfull",
                        'image'=>$icon,
                    )
                );
                $post_data3 = json_encode($fields3);
                $url = "https://fcm.googleapis.com/fcm/send";
                $ch3  = curl_init($url); 
                curl_setopt($ch3, CURLOPT_FAILONERROR, TRUE); 
                curl_setopt($ch3, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, 0); 
                curl_setopt($ch3, CURLOPT_CONNECTTIMEOUT, 3);
                curl_setopt($ch3, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch3, CURLOPT_POSTFIELDS, $post_data3);
                curl_setopt($ch3, CURLOPT_HTTPHEADER, array($settingdata->googleapi_authkey,
                    'Content-Type: application/json')
                );
                $result3 = curl_exec($ch3);
                curl_close($ch3);             
                        
                
            }else{
                $json['response'] = [
                    'status'     => 'error',
                    'message'    => 'Please Try Again !',
                    
                ]; 
            }
        }else{
            $json['response'] = [
                'status'     => 'error',
                'message'    => 'You Already Enjoyed All leaves',
                
            ];    
        }
        echo json_encode($json,JSON_UNESCAPED_UNICODE);
        
    }

    /*
    -------------------------------------------------------------------
    |
    |LEAVE  LIST
    |
    --------------------------------------------------------------------
    */
    public function leaveList(Request $request){

        $start=$request->get('start');
        $employee_id = $request->get('employee_id');

        $countdata =  $this->count_leave($employee_id);

        if(empty($start)){
            $leavelist = $this->leave_list($employee_id);
        }else{
            $leavelist = $this->leave_listlimit($employee_id,$start);   
        }
        
        if(!empty($leavelist)){
            $json['response'] = [
                'status'        => 'ok',
                'type_list'     => $leavelist,
                'length'        => $countdata,
                'message'       => localize('found_some_data'),
            
            ];
        }else{
            $json['response'] = [
                'status'     => 'error',
                'message'    => localize('no_record_found'),
            
            ]; 
        }
        
        echo json_encode($json,JSON_UNESCAPED_UNICODE);
    }

     /*
    -------------------------------------------------------------------
    |
    |Ledger ******** As of now accounts module not available , so ledger data will be null
    |
    --------------------------------------------------------------------
    */

    public function ledger(){

        $json['response'] = [
            'status'     => 'error',
            'length'        => "",
            'type_list'     => [],
            'message'    =>  localize('no_record_found'),
        
        ]; 
        
        echo json_encode($json,JSON_UNESCAPED_UNICODE);
    }

    /*-----------------------------------------------------
    | Leave Remaining info
    |
    |------------------------------------------------
    */

    public function leaveRemaining(Request $request){
        
        
        $employee_id = $request->get('employee_id');
        $totalremaining = $this->get_leave_remaining($employee_id);
        
        if(!empty($totalremaining)){
            $json['response'] = [
                'status'      => 'ok',
                'total'        =>  "$totalremaining",
                'message'     => localize('found_some_data'),
                
            ];
            
        }else{
            $json['response'] = [
                'status'     => 'error',
                'total'        => "",
                'message'    => localize('no_record_found'),
                
            ]; 
        }
        echo json_encode($json,JSON_UNESCAPED_UNICODE);
        
    }

    /*-----------------------------------------------------
    | Total Working Day of current month
    |
    |------------------------------------------------
    */

    public function currentMonthTotalday(Request $request){
        
        
        $query_date = date('Y-m-d');
        $employee_id = $request->get('employee_id');
        $fromdate = date('Y-m-01', strtotime($query_date));
        $todate = date('Y-m-t', strtotime($query_date));

        $alldays = $this->attendance_totalday_currentmonth($employee_id,$fromdate,$todate);
    
        if(!empty($alldays)){
            $json['response'] = [
                'status'      => 'ok',
                'totalday'    =>  "$alldays",
                'message'     => localize('no_record_found'),
                
            ];
            
        }else{
            $json['response'] = [
                'status'     => 'error',
                'totalday' =>  "",
                'message'    => localize('no_record_found'),
                
            ]; 
        }

        echo json_encode($json,JSON_UNESCAPED_UNICODE);
        
    }

    public function get_leave_remaining($id){

        $totalleave = $totalleave = LeaveType::select(DB::raw('*, sum(leave_days) as totalleave'))->first();
        $totaltaken = ApplyLeave::select(DB::raw('*, sum(total_approved_day) as takenlv'))
        ->where('employee_id', $id)
        ->first();
        
        $remainingleave = (!empty($totalleave)?$totalleave->totalleave:0) - (!empty($totaltaken)?$totaltaken->takenlv:0);
        return $remainingleave;

    }

    public function leave_listlimit($employee_id,$limit){

        return ApplyLeave::select('leave_apply_start_date as fromdate', 'leave_apply_end_date as todate', 'total_apply_day as apply_day', 'reason', 'is_approved as status')
        ->where('employee_id', $employee_id)
        ->where('deleted_at', Null)
        ->orderBy('id', 'desc')
        ->limit($limit)
        ->get()
        ->toArray();
   }

    public function leave_list($employee_id){

        return ApplyLeave::select('leave_apply_start_date as fromdate', 'leave_apply_end_date as todate', 'total_apply_day as apply_day', 'reason', 'is_approved as status')
            ->where('employee_id', $employee_id)
            ->where('deleted_at', Null)
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();

    }

    public function count_leave($employee_id){

        $leaveAppliesCount = ApplyLeave::where('employee_id', $employee_id)->count();
        if ($leaveAppliesCount > 0) {
            return $leaveAppliesCount;
        }
        
        return false;

    }

    public function insert_leave_application($data = array())
    {
        return ApplyLeave::create($data);
    }

    public function type_list(){

        return LeaveType::all()->toArray();
    }

    public function payroll_salaryinfolimit($id,$limit){

        $data = DB::table('salary_generates as gs')
        ->select('gs.*', 
                DB::raw("CONCAT_WS(' ', b.first_name, b.last_name) AS employee_name"), 
                DB::raw("'2' AS salarytype"), 
                'gss.is_approved as approved')
        ->leftJoin('employees as b', 'b.id', '=', 'gs.employee_id')
        ->leftJoin('salary_sheet_generates as gss', 'gss.name', '=', 'gs.salary_month_year')
        ->where('gs.employee_id', $id)
        ->where('gs.deleted_at',NULL)
        ->groupBy('gs.id')
        ->limit($limit)
        ->get()
        ->toArray();


        return $data;
          
      }

    public function payroll_salaryinfo($id){

        $data = DB::table('salary_generates as gs')
        ->select('gs.*',DB::raw("CONCAT_WS(' ', b.first_name, b.last_name) AS employee_name"),'gss.is_approved as approved', DB::raw("'2' as salarytype"))
        ->leftJoin('employees as b', 'b.id', '=', 'gs.employee_id')
        ->leftJoin('salary_sheet_generates as gss', 'gss.name', '=', 'gs.salary_month_year')
        ->where('gs.employee_id', $id)
        ->where('gs.deleted_at',NULL)
        ->groupBy('gs.id')
        ->get()
        ->toArray();

        return $data;
       
    }

    public function count_payroll_salaryinfo($id){

        $count = SalaryGenerate::where('employee_id', $id)->count();

        return $count;

    }

    public function totaldayofcurrentstage(){

        $query_date = date('Y-m-d');
        $fromdate = date('Y-m-01', strtotime($query_date));
        $todate = date('Y-m-d');

        $begin = Carbon::createFromFormat('Y-m-d', $fromdate);
        $end = Carbon::createFromFormat('Y-m-d', $todate)->addDay(); // Add one day to include the end date

        $daterange = $begin->toPeriod($end, '1 day');

        $result = 0;
        foreach ($daterange as $date) {
            $result += 1;
        }

        return $result;

    }

    public function weekends(){

        $query_date = date('Y-m-d');
        $fromdate = date('Y-m-01', strtotime($query_date));
        $todate = date('Y-m-d');  
                
        $wknd = WeekHoliday::first();

        $holidays = $wknd->dayname;
        
        $weeklyholiday = array();
        $weeklyholiday = array_map('trim', explode(',', $holidays));
        $existdata = 0;
        
        if (sizeof($weeklyholiday) > 0) {
            foreach($weeklyholiday as $days){

                $begin = Carbon::createFromFormat('Y-m-d', $fromdate);
                $end = Carbon::createFromFormat('Y-m-d', $todate)->addDay(); // Add one day to include the end date

                $daterange = $begin->toPeriod($end, '1 day');

                foreach ($daterange as $date) {
                    $dates = strtolower($date->format('l'));
                    // return $dates;
                    if ($days == $dates) {
                        $existdata += 1;
                    } else {
                        $existdata += 0;
                    }
                }
                
            }
        }
        
        return $existdata;        
                
    }

    public function takenleave($id){

        $totalTaken = ApplyLeave::selectRaw('*, sum(total_approved_day) as takenlv')
        ->where('employee_id', $id)
        ->first();

        $takenLv = !empty($totalTaken) ? $totalTaken->takenlv : 0;

        return $takenLv;

    }

    public function attendance_totalday_currentmonth($id,$from_date,$to_date){

        return Attendance::select('*', DB::raw('DATE(time) as mydate'))
        ->where('employee_id', $id)
        ->whereDate('time', '>=', $from_date)
        ->whereDate('time', '<=', $to_date)
        ->groupBy('mydate')
        ->orderByDesc('time')
        ->get()->count();
    }

    public function notice_board($start){
        // Fetch all notices ordered by notice_id in descending order
        $notices = Notice::query()
            ->withoutGlobalScope('sortByLatest')
            ->orderBy('notice_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit($start)
            ->get()
            ->toArray();

        return $notices;
    }

    public function notice_boardall(){
        // Fetch all notices ordered by notice_id in descending order
        $notices = Notice::query()
            ->withoutGlobalScope('sortByLatest')
            ->orderBy('notice_date', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();

        return $notices;
    }

    public function count_notice(){
       return Notice::count();
    }

    public function attendance_history_datewise($id,$from_date,$to_date){

        $from_date = Carbon::parse($from_date)->format('Y-m-d');
        $to_date = Carbon::parse($to_date)->format('Y-m-d');
        $policy = $this->attendancePolicy();

        $rows = Attendance::query()
            ->select(['employee_id', 'time', 'machine_state', 'exception_flag', 'exception_reason'])
            ->where('employee_id', $id)
            ->whereDate('time', '>=', $from_date)
            ->whereDate('time', '<=', $to_date)
            ->orderBy('time', 'asc')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $attendance = [];
        $groupedByDate = $rows
            ->groupBy(function ($row) {
                return Carbon::parse((string) $row->time)->format('Y-m-d');
            })
            ->sortKeysDesc();

        foreach ($groupedByDate as $date => $dayRows) {
            $orderedRows = $dayRows->sortBy('time')->values();
            $firstPunch = Carbon::parse((string) $orderedRows->first()->time);
            $lastPunch = Carbon::parse((string) $orderedRows->last()->time);

            $totalWorkedSeconds = max(0, $lastPunch->diffInSeconds($firstPunch, false));

            $breakSeconds = 0;
            $timePoints = $orderedRows
                ->map(function ($row) {
                    return Carbon::parse((string) $row->time);
                })
                ->values();

            $totalPoints = $timePoints->count();
            for ($i = 1; $i < ($totalPoints - 1); $i += 2) {
                $outTime = $timePoints->get($i);
                $nextInTime = $timePoints->get($i + 1);

                if ($outTime && $nextInTime) {
                    $gap = $nextInTime->diffInSeconds($outTime, false);
                    if ($gap > 0) {
                        $breakSeconds += $gap;
                    }
                }
            }

            $netSeconds = max(0, $totalWorkedSeconds - $breakSeconds);
            $statusMeta = $this->buildAttendanceDayStatus($date, $firstPunch, $lastPunch, $totalPoints, $orderedRows, $policy);

            $baseRow = [
                'intime' => $firstPunch->format('Y-m-d H:i:s'),
                'outtime' => $lastPunch->format('Y-m-d H:i:s'),
                'uid' => (int) $id,
                'time' => $lastPunch->format('Y-m-d H:i:s'),
                'totalhours' => $this->formatSecondsToClock($totalWorkedSeconds),
                'date' => (string) $date,
                'punchtime' => $lastPunch->format('H:i:s'),
            ];

            // Keep legacy response shape: row payload at index 0 + summary keys.
            $entry = [$baseRow];
            $entry['totalhours'] = $baseRow['totalhours'];
            $entry['date'] = (string) $date;
            $entry['wastage'] = $this->formatSecondsToClock($breakSeconds);
            $entry['nethours'] = $this->formatSecondsToClock($netSeconds);
            $entry['punch_count'] = $totalPoints;
            $entry['is_complete_day'] = ($totalPoints % 2) === 0;
            $entry['first_punch'] = $firstPunch->format('Y-m-d H:i:s');
            $entry['last_punch'] = $lastPunch->format('Y-m-d H:i:s');
            $entry['attendance_status'] = $statusMeta['status'];
            $entry['has_exception'] = $statusMeta['has_exception'];
            $entry['exception_reason'] = $statusMeta['exception_reason'];
            $entry['late_minutes'] = $statusMeta['late_minutes'];
            $entry['early_leave_minutes'] = $statusMeta['early_leave_minutes'];
            $entry['policy_start'] = $statusMeta['policy_start'];
            $entry['policy_end'] = $statusMeta['policy_end'];
            $entry['late_grace_minutes'] = $policy['late_grace_minutes'];
            $entry['early_leave_grace_minutes'] = $policy['early_leave_grace_minutes'];

            $attendance[] = $entry;
        }

        return $attendance;
       
   }

    protected function formatSecondsToClock(int $seconds): string
    {
        $seconds = max(0, $seconds);

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
    }

    protected function attendancePolicy(): array
    {
        $pointSetting = PointSettings::query()
            ->select(['attendance_start', 'attendance_end'])
            ->first();

        $lateGrace = (int) config('humanresource.attendance.late_grace_minutes', 10);
        $earlyGrace = (int) config('humanresource.attendance.early_leave_grace_minutes', 10);

        return [
            'attendance_start' => $this->normalizePolicyTime($pointSetting?->attendance_start),
            'attendance_end' => $this->normalizePolicyTime($pointSetting?->attendance_end),
            'late_grace_minutes' => max(0, $lateGrace),
            'early_leave_grace_minutes' => max(0, $earlyGrace),
        ];
    }

    protected function normalizePolicyTime($value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse(trim($value))->format('H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function dateTimeFromPolicy(string $date, ?string $time): ?Carbon
    {
        if (!is_string($time) || trim($time) === '') {
            return null;
        }

        try {
            return Carbon::parse($date . ' ' . trim($time));
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function buildAttendanceDayStatus(
        string $date,
        Carbon $firstPunch,
        Carbon $lastPunch,
        int $punchCount,
        $orderedRows,
        array $policy
    ): array {
        $hasException = ($punchCount % 2) !== 0;
        $exceptionReason = null;

        foreach ($orderedRows as $row) {
            if ((bool) ($row->exception_flag ?? false)) {
                $hasException = true;
            }

            $rowReason = trim((string) ($row->exception_reason ?? ''));
            if ($rowReason !== '' && $exceptionReason === null) {
                $exceptionReason = $rowReason;
            }
        }

        if ($hasException && $exceptionReason === null) {
            $exceptionReason = 'UNPAIRED_PUNCH';
        }

        $policyStartAt = $this->dateTimeFromPolicy($date, $policy['attendance_start'] ?? null);
        $policyEndAt = $this->dateTimeFromPolicy($date, $policy['attendance_end'] ?? null);

        $lateMinutes = 0;
        if ($policyStartAt) {
            $lateSeconds = $firstPunch->getTimestamp() - $policyStartAt->getTimestamp();
            if ($lateSeconds > 0) {
                $lateMinutes = (int) ceil($lateSeconds / 60);
            }
        }

        $earlyLeaveMinutes = 0;
        if ($policyEndAt) {
            $earlySeconds = $policyEndAt->getTimestamp() - $lastPunch->getTimestamp();
            if ($earlySeconds > 0) {
                $earlyLeaveMinutes = (int) ceil($earlySeconds / 60);
            }
        }

        $lateGrace = (int) ($policy['late_grace_minutes'] ?? 0);
        $earlyGrace = (int) ($policy['early_leave_grace_minutes'] ?? 0);
        $isLate = $lateMinutes > $lateGrace;
        $isEarlyLeave = $earlyLeaveMinutes > $earlyGrace;

        if ($hasException) {
            $status = 'PARTIAL';
        } elseif ($isLate && $isEarlyLeave) {
            $status = 'LATE_EARLY_LEAVE';
        } elseif ($isLate) {
            $status = 'LATE';
        } elseif ($isEarlyLeave) {
            $status = 'EARLY_LEAVE';
        } else {
            $status = 'PRESENT';
        }

        return [
            'status' => $status,
            'has_exception' => $hasException,
            'exception_reason' => $exceptionReason,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'policy_start' => $policyStartAt?->format('Y-m-d H:i:s'),
            'policy_end' => $policyEndAt?->format('Y-m-d H:i:s'),
        ];
    }

    protected function attendanceSummary(array $attendance): array
    {
        $summary = [
            'total_days' => count($attendance),
            'complete_days' => 0,
            'incomplete_days' => 0,
            'present_days' => 0,
            'late_days' => 0,
            'early_leave_days' => 0,
            'late_early_leave_days' => 0,
            'partial_days' => 0,
            'total_worked_hours' => '00:00:00',
            'total_net_hours' => '00:00:00',
            'average_net_hours_per_day' => '00:00:00',
        ];

        $totalWorkedSeconds = 0;
        $totalNetSeconds = 0;

        foreach ($attendance as $row) {
            $status = strtoupper((string) ($row['attendance_status'] ?? 'PRESENT'));
            $isComplete = (bool) ($row['is_complete_day'] ?? false);
            if ($isComplete) {
                $summary['complete_days']++;
            } else {
                $summary['incomplete_days']++;
            }

            if ($status === 'PARTIAL') {
                $summary['partial_days']++;
            } elseif ($status === 'LATE_EARLY_LEAVE') {
                $summary['late_early_leave_days']++;
            } elseif ($status === 'LATE') {
                $summary['late_days']++;
            } elseif ($status === 'EARLY_LEAVE') {
                $summary['early_leave_days']++;
            } else {
                $summary['present_days']++;
            }

            $totalWorkedSeconds += $this->clockToSeconds((string) ($row['totalhours'] ?? '00:00:00'));
            $totalNetSeconds += $this->clockToSeconds((string) ($row['nethours'] ?? '00:00:00'));
        }

        $summary['total_worked_hours'] = $this->formatSecondsToClock($totalWorkedSeconds);
        $summary['total_net_hours'] = $this->formatSecondsToClock($totalNetSeconds);
        $summary['average_net_hours_per_day'] = $summary['total_days'] > 0
            ? $this->formatSecondsToClock((int) floor($totalNetSeconds / $summary['total_days']))
            : '00:00:00';

        return $summary;
    }

    protected function clockToSeconds(string $clock): int
    {
        $parts = explode(':', trim($clock));
        if (count($parts) !== 3) {
            return 0;
        }

        if (!is_numeric($parts[0]) || !is_numeric($parts[1]) || !is_numeric($parts[2])) {
            return 0;
        }

        $hours = (int) $parts[0];
        $minutes = (int) $parts[1];
        $seconds = (int) $parts[2];

        return max(0, ($hours * 3600) + ($minutes * 60) + $seconds);
    }

    /**
     * Calculating totalNetworkHours for an employee current_day
     */
    public function employee_worked_hour_by_date($employee_id, $mydate)
    {

        $totalhour = 0;
        $totalwasthour = 0;
        $totalnetworkhour = 0;

        $attenddata = DB::table('attendances as a')
            ->select('a.time', DB::raw('MIN(a.time) as intime'), DB::raw('MAX(a.time) as outtime'), 'a.employee_id as uid')
            ->where('a.time', 'LIKE', '%' . date("Y-m-d", strtotime($mydate)) . '%')
            ->where('a.employee_id', $employee_id)
            ->whereNull('a.deleted_at')
            ->get();

        // Getting totalWorkHours
        $date_a = Carbon::createFromFormat('Y-m-d H:i:s', $attenddata[0]->outtime);
        $date_b = Carbon::createFromFormat('Y-m-d H:i:s', $attenddata[0]->intime);
        $interval = $date_a->diff($date_b);

        $totalwhour = $interval->format('%h:%i:%s');

        // End of Getting totalWorkHours

        $att_dates = date("Y-m-d", strtotime($attenddata[0]->time));
        // Convert the given date to a Carbon instance
        $exist_date = Carbon::createFromFormat('Y-m-d', $att_dates);
        // Get the next day's date
        $nextDayDate = $exist_date->addDay()->toDateString();
        $att_in = DB::table('attendances as a')
            ->select('a.*', 'b.first_name', 'b.last_name')
            ->leftJoin('employees as b', 'a.employee_id', '=', 'b.id')
            // ->where('a.time', 'LIKE', '%' . $att_dates)
            ->where('a.employee_id', $attenddata[0]->uid)
            ->whereRaw("a.time > ?", [$att_dates])
            ->whereRaw("a.time < ?", [$nextDayDate])
            ->whereNull('a.deleted_at')
            ->orderBy('a.time', 'ASC')
            ->get();

        $ix = 1;
        $in_data = [];
        $out_data = [];
        foreach ($att_in as $attendancedata) {

            if ($ix % 2) {
                $status = "IN";
                $in_data[$ix] = $attendancedata->time;
            } else {
                $status = "OUT";
                $out_data[$ix] = $attendancedata->time;
            }
            $ix++;
        }

        $result_in = array_values($in_data);
        $result_out = array_values($out_data);
        $total = [];
        $count_out = count($result_out);

        if ($count_out >= 2) {
            $n_out = $count_out - 1;
        } else {
            $n_out = 0;
        }
        for ($i = 0; $i < $n_out; $i++) {

            $date_a = Carbon::parse($result_in[$i + 1]);
            $date_b = Carbon::parse($result_out[$i]);
            $interval = $date_a->diff($date_b);

            $total[$i] = $interval->format('%h:%i:%s');
        }

        $hou = 0;
        $min = 0;
        $sec = 0;
        $totaltime = '00:00:00';
        $length = sizeof($total);

        for ($x = 0; $x <= $length; $x++) {
            $split = explode(":", @$total[$x]);
            $hou += @(int)$split[0];
            $min += @$split[1];
            $sec += @$split[2];
        }

        $seconds = $sec % 60;
        $minutes = $sec / 60;
        $minutes = (int)$minutes;
        $minutes += $min;
        $hours = $minutes / 60;
        $minutes = $minutes % 60;
        $hours = (int)$hours;
        $hours += $hou % 24;

        $totalwasthour = $hours . ":" . $minutes . ":" . $seconds;

        $date_a = Carbon::parse($totalwhour);
        $date_b = Carbon::parse($totalwasthour);
        $networkhours = $date_a->diff($date_b);

        $totalnetworkhour = $networkhours->format('%h:%i:%s');

        return [
            "totalwasthour" => $totalwasthour,
            "totalnetworkhour" => $totalnetworkhour,
        ];
    }

    public function checkUser($userData)
    {
        // Attempt to authenticate the user
        if (Auth::attempt(['email' => $userData['email'], 'password' => $userData['password']])) {
            // Authentication successful
            $user = Auth::user();
            // Return user data or any response you want
            return $user;
        }
        return false;
    }

    public function userData($email)
    {
        $employeeData = Employee::select('employees.*', 'departments.department_name', 'users.profile_image as profile_pic', 'users.token_id')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->leftJoin('users', 'users.email', '=', 'employees.email')
            ->where('employees.email', $email)
            ->where('users.user_type_id', 2)
            ->first();

        return $employeeData;
    }
}
