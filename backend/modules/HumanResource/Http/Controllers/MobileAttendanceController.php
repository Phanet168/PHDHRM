<?php

namespace Modules\HumanResource\Http\Controllers;

use App\Models\Appsetting;
use App\Models\AttendanceScanLog;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Modules\HumanResource\Entities\Attendance;
use Modules\HumanResource\Entities\AttendanceAdjustment;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Shift;
use Modules\HumanResource\Services\AttendanceCaptureService;
use Modules\HumanResource\Services\AttendanceStatusService;
use Modules\HumanResource\Services\QrAttendanceTokenService;
use Modules\HumanResource\Services\ShiftResolverService;
use Modules\HumanResource\Support\AttendanceUnitScope;
use Modules\HumanResource\Support\RequestCache;

class MobileAttendanceController extends Controller
{
    public function __construct(private readonly AttendanceStatusService $status, private readonly ShiftResolverService $shifts)
    {
    }

    private function employee(Request $request): Employee
    {
        // Self-service APIs never accept an identity or unit selected by the client.
        $request->validate(['employee_id' => ['prohibited'], 'user_id' => ['prohibited'], 'department_id' => ['prohibited'], 'workplace_id' => ['prohibited']]);
        $employee = $request->user()?->employee()->where('is_active', 1)->first();
        if (! $employee) {
            throw ValidationException::withMessages(['employee' => 'This account has no active employee profile.']);
        }

        return $employee;
    }

    private function ok(array $payload, int $status = 200): JsonResponse
    {
        return response()->json(['response' => ['status' => 'ok', ...$payload]], $status);
    }

    public function today(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $time = now();
        $workDate = $this->shifts->workDateForPunch($employee->id, $time);

        return $this->ok(['data' => $this->day($employee, $workDate), 'meta' => [
            'server_time' => $time->toIso8601String(), 'timezone' => config('app.timezone'),
            'next_punch_type' => AttendanceCaptureService::nextPunchType($employee->id, $time),
            'geofence' => $this->geofence(Department::find(AttendanceUnitScope::employeeUnit($employee))),
        ]]);
    }

    public function history(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        [$from, $to] = $this->range($request);
        // Future roster dates are returned by schedule, not counted as absence in history.
        $lastDay = $to->min(now()->startOfDay());
        $days = [];
        if ($from->lte($lastDay)) {
            // One query for the whole range instead of one per day — day()
            // used to look this up itself, which meant N extra queries for
            // an N-day history request.
            $adjustmentNotes = $this->adjustmentNotesForRange($employee->id, $from, $lastDay);
            foreach (CarbonPeriod::create($from, $lastDay) as $date) {
                $days[] = $this->day($employee, $date, $adjustmentNotes);
            }
        }

        return $this->ok(['data' => array_reverse($days), 'meta' => [
            'from_date' => $from->toDateString(), 'to_date' => $to->toDateString(), 'timezone' => config('app.timezone'),
        ]]);
    }

    /** A ten-minute link to the caller's own printable attendance report for a range (max 31 days). */
    public function reportLink(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        [$from, $to] = $this->range($request);

        return $this->ok(['data' => ['url' => URL::temporarySignedRoute('api.attendance.report.signed', now()->addMinutes(10), [
            'employee' => $employee->id, 'from' => $from->toDateString(), 'to' => $to->toDateString(),
        ])]]);
    }

    /**
     * The printable report behind a signed link (opened in the phone's browser, so no token):
     * every day of the range read exactly as history() reads it, a summary and signature lines.
     */
    public function signedReport(Request $request)
    {
        $data = $request->validate([
            'employee' => ['required', 'integer'], 'from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d'],
        ]);
        $employee = Employee::query()->findOrFail($data['employee']);
        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->startOfDay();
        abort_if($to->lt($from) || $from->diffInDays($to) > 30, 404);
        $lastDay = $to->min(now()->startOfDay());
        $days = [];
        if ($from->lte($lastDay)) {
            $notes = $this->adjustmentNotesForRange($employee->id, $from, $lastDay);
            foreach (CarbonPeriod::create($from, $lastDay) as $date) {
                $days[] = $this->day($employee, $date, $notes);
            }
        }
        $count = fn (array $codes) => count(array_filter($days, fn ($d) => in_array($d['attendance_status'], $codes, true)
            && ! ($d['is_provisional'] && in_array($d['attendance_status'], ['absent', 'incomplete'], true))));
        $summary = [
            'present' => $count(['on_time', 'late', 'early_leave', 'late_and_early_leave']),
            'late' => count(array_filter($days, fn ($d) => $d['late_minutes'] > 0)),
            'early' => count(array_filter($days, fn ($d) => $d['early_leave_minutes'] > 0)),
            'absent' => $count(['absent']), 'incomplete' => $count(['incomplete']),
            'leave' => $count(['leave']), 'mission' => $count(['mission']),
            'off' => $count(['holiday', 'day_off']),
            'worked_minutes' => array_sum(array_column($days, 'worked_minutes')),
            'late_minutes' => array_sum(array_column($days, 'late_minutes')),
            'early_minutes' => array_sum(array_column($days, 'early_leave_minutes')),
        ];
        $position = RequestCache::hasTable('positions') ? $employee->position()->first() : null;

        return response()->view('humanresource::attendance.report', [
            'employee' => $employee, 'position' => $position?->position_name_km ?: $position?->position_name,
            'unit' => $days[0]['unit']['name'] ?? null, 'days' => $days, 'summary' => $summary, 'from' => $from, 'to' => $to,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function schedule(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        [$from, $to] = $this->range($request);
        $days = [];
        $adjustmentNotes = $this->adjustmentNotesForRange($employee->id, $from, $to);
        foreach (CarbonPeriod::create($from, $to) as $date) {
            $daily = $this->day($employee, $date, $adjustmentNotes);
            $days[] = [
                'date' => $daily['date'], 'unit' => $daily['unit'], 'shift' => $daily['shift'], 'duty_shift' => $daily['duty_shift'],
                'shift_source' => $daily['shift_source'], 'is_day_off' => $daily['is_day_off'],
                'is_holiday' => $daily['is_holiday'], 'holiday_name' => $daily['holiday_name'] ?? null,
                'is_on_leave' => $daily['attendance_status'] === 'leave',
                'is_on_mission' => $daily['attendance_status'] === 'mission',
                'sessions' => array_map(fn ($s) => array_intersect_key($s, array_flip(['name', 'scheduled_in', 'scheduled_out'])), $daily['sessions']),
            ];
        }

        return $this->ok(['data' => $days, 'meta' => ['timezone' => config('app.timezone')]]);
    }

    private function range(Request $request): array
    {
        $data = $request->validate(['from_date' => ['nullable', 'date_format:Y-m-d'], 'to_date' => ['nullable', 'date_format:Y-m-d']]);
        $from = Carbon::parse($data['from_date'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($data['to_date'] ?? $from->copy()->endOfMonth()->toDateString())->startOfDay();
        if ($to->lt($from) || $from->diffInDays($to) > 30) {
            throw ValidationException::withMessages(['to_date' => 'Choose a range of 1 to 31 days, ending on or after from_date.']);
        }

        return [$from, $to];
    }

    /**
     * @param  array<string, string>|null  $adjustmentNotes  Pre-fetched date => reason map from
     *                                                        adjustmentNotesForRange(), for callers that loop over
     *                                                        many days. Null means "look this one day up directly"
     *                                                        — fine for today()/scan()'s single-day calls.
     */
    private function day(Employee $employee, Carbon $date, ?array $adjustmentNotes = null): array
    {
        $daily = $this->status->determineDailyStatus($employee->id, $date);
        $resolved = $this->shifts->resolveForDate($employee->id, $date);
        $shift = $resolved['shift'] ?? $resolved['duty'] ?? null;
        $duty = isset($resolved['shift']) ? ($resolved['duty'] ?? null) : null;
        $unitId = AttendanceUnitScope::employeeUnit($employee);
        $unit = RequestCache::remember('department:'.$unitId, fn () => Department::find($unitId));
        $sessions = $daily['policy_payload']['sessions'] ?? [];
        $status = match ($daily['attendance_status']) {
            'Present' => 'on_time', 'On Mission' => 'mission', 'On Leave' => 'leave',
            'Early Leave' => 'early_leave', 'Day Off' => 'day_off',
            default => strtolower($daily['attendance_status']),
        };
        if ($status === 'late' && $daily['early_leave_minutes'] > 0) {
            $status = 'late_and_early_leave';
        }
        // Today's unfinished sessions are provisional; clients must not present them as final absence.
        $provisional = $date->isToday() || Carbon::parse($daily['policy_payload']['window_end'])->gt(now());
        $minutes = (int) $daily['worked_minutes'];
        $overtimeMinutes = (int) ($daily['overtime_minutes'] ?? 0);
        $adjustmentNote =
            $adjustmentNotes !== null
                ? ($adjustmentNotes[$date->toDateString()] ?? null)
                : $this->adjustmentNote($employee->id, $date);

        return [
            'date' => $date->toDateString(), 'employee_id' => (int) $employee->id,
            'unit' => ['id' => $unitId ?: null, 'name' => $unit?->department_name],
            'attendance_status' => $status, 'is_provisional' => $provisional,
            'in_time' => $daily['in_time'], 'out_time' => $daily['out_time'],
            'worked_minutes' => $minutes, 'total_hours' => sprintf('%d:%02d:00', intdiv($minutes, 60), $minutes % 60),
            // Minutes clocked out past the shift's scheduled end; 0 when there's no shift
            // to measure against or the day is exempt (leave/mission/holiday/day off).
            'overtime_minutes' => $overtimeMinutes,
            'late_minutes' => (int) $daily['late_minutes'], 'early_leave_minutes' => (int) $daily['early_leave_minutes'],
            'early_arrival_minutes' => (int) ($daily['policy_payload']['early_arrival_minutes'] ?? 0),
            'punch_count' => array_sum(array_column($sessions, 'punch_count')),
            'has_exception' => $status === 'incomplete', 'exception_reason' => $status === 'incomplete' ? 'UNPAIRED_PUNCH' : null,
            // HR's stated reason for an approved punch-time correction on this day, if any.
            'adjustment_note' => $adjustmentNote,
            'is_day_off' => (bool) $daily['is_day_off'], 'is_holiday' => (bool) $daily['is_holiday'],
            'holiday_name' => $daily['policy_payload']['holiday_name'] ?? null,
            'shift_source' => $resolved['source'] ?? null,
            // `shift` is the day's main schedule (the duty itself on a duty-only day);
            // `duty_shift` is a night/on-call duty added after the regular hours.
            'shift' => $this->shiftPayload($shift),
            'duty_shift' => $this->shiftPayload($duty),
            'sessions' => $sessions,
        ];
    }

    private function shiftPayload(?Shift $shift): ?array
    {
        return $shift ? [
            'id' => (int) $shift->id, 'name' => $shift->name, 'code' => $shift->code,
            'start_time' => $shift->start_time, 'morning_end_time' => $shift->morning_end_time,
            'afternoon_start_time' => $shift->afternoon_start_time, 'end_time' => $shift->end_time,
            'is_cross_day' => (bool) $shift->is_cross_day, 'is_duty' => (bool) $shift->is_duty,
            'grace_late_minutes' => (int) $shift->grace_late_minutes,
            'grace_early_leave_minutes' => (int) $shift->grace_early_leave_minutes,
        ] : null;
    }

    // HR's own explanation for an approved punch-time correction that lands on this
    // date — an audit note, not an employee-editable field. Most recent approval wins.
    private function adjustmentNote(int $employeeId, Carbon $date): ?string
    {
        $day = $date->toDateString();
        $reason = AttendanceAdjustment::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where(function ($query) use ($day) {
                $query->whereDate('new_time', $day)->orWhereDate('old_time', $day);
            })
            ->orderByDesc('approved_at')
            ->value('reason');
        $reason = trim((string) $reason);
        return $reason === '' ? null : $reason;
    }

    /**
     * Same as adjustmentNote() but for a whole date range in one query —
     * used by history()/schedule() so an N-day request doesn't fire N of
     * these. Returns a Y-m-d => reason map; later approvals overwrite
     * earlier ones for the same date, same "most recent wins" rule.
     *
     * @return array<string, string>
     */
    private function adjustmentNotesForRange(int $employeeId, Carbon $from, Carbon $to): array
    {
        $notes = [];
        $windowEnd = $to->copy()->endOfDay();
        AttendanceAdjustment::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where(function ($query) use ($from, $windowEnd) {
                $query->whereBetween('new_time', [$from, $windowEnd])
                    ->orWhereBetween('old_time', [$from, $windowEnd]);
            })
            ->orderBy('approved_at')
            ->get(['new_time', 'old_time', 'reason'])
            ->each(function ($adjustment) use (&$notes) {
                $reason = trim((string) $adjustment->reason);
                if ($reason === '') {
                    return;
                }
                foreach ([$adjustment->new_time, $adjustment->old_time] as $time) {
                    if ($time) {
                        $notes[Carbon::parse($time)->toDateString()] = $reason;
                    }
                }
            });

        return $notes;
    }

    public function scan(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'], 'longitude' => ['required', 'numeric', 'between:-180,180'],
            'qr_token' => ['nullable', 'string', 'max:4096'], 'request_id' => ['required', 'uuid'],
            'datetime' => ['prohibited'], 'machine_state' => ['prohibited'],
            'offline' => ['sometimes', 'boolean'],
        ]);
        // A scan the app saved without internet and is sending now: it is stamped with when it
        // was captured — only within the configured window, and a QR code must have been valid
        // then. Online scans always use the server's clock.
        $offline = $request->boolean('offline');
        $scannedAt = now();
        if ($offline) {
            $request->validate([
                'captured_at' => ['required', 'date'],
                'clock_source' => ['nullable', 'in:server_anchored,device'],
            ]);
            $scannedAt = Carbon::parse($request->input('captured_at'))->setTimezone(config('app.timezone'))->startOfSecond();
            $maxHours = (int) config('humanresource.attendance.offline_max_hours', 24);
            if ($scannedAt->gt(now()->addMinutes(2))) {
                return $this->scanError($request, $employee, 'offline_time_invalid', 'The scan time is in the future.', 422);
            }
            if ($scannedAt->lt(now()->subHours($maxHours))) {
                return $this->scanError($request, $employee, 'offline_scan_expired', "Offline scans must be sent within {$maxHours} hours.", 422);
            }
        }
        $unitId = AttendanceUnitScope::employeeUnit($employee);
        $unit = Department::find($unitId);
        if (! $unit) {
            return $this->scanError($request, $employee, 'workplace_not_found', 'Your organization unit is not configured.', 422);
        }
        $token = trim($data['qr_token'] ?? '');
        if ($token !== '') {
            try {
                $claims = QrAttendanceTokenService::verify($token, $offline ? $scannedAt : null);
            } catch (ValidationException $e) {
                return $this->scanError($request, $employee, 'invalid_qr', 'Invalid or expired QR token.', 422);
            }
            if ((int) $claims['wid'] !== $unitId) {
                return $this->scanError($request, $employee, 'wrong_workplace', 'Use the QR code for your organization unit.', 403);
            }
        } elseif (config('humanresource.attendance.require_qr_token', false)) {
            return $this->scanError($request, $employee, 'qr_token_required', 'QR token is required.', 422);
        }
        $fence = $this->geofence($unit);
        if (! $fence) {
            return $this->scanError($request, $employee, 'geofence_not_configured', 'Attendance geofence is not configured.', 422);
        }
        ['latitude' => $lat, 'longitude' => $lng, 'radius_meters' => $radius, 'source' => $source] = $fence;
        $distance = $this->distance($lat, $lng, (float) $data['latitude'], (float) $data['longitude']);
        $geo = ['range' => round($distance, 1), 'acceptable_range' => $radius, 'geofence_source' => $source,
            'workplace_id' => $unitId, 'workplace_name' => $unit->department_name];
        if ($distance > $radius) {
            return $this->scanError($request, $employee, 'out_of_range', 'You are outside the attendance area.', 422, $geo);
        }
        // The reference marks offline punches so HR can tell them apart.
        $reference = ($offline ? 'mobile_offline:' : 'mobile_request:').$data['request_id'];
        [$attendance, $duplicate] = DB::transaction(function () use ($employee, $data, $reference, $unitId, $token, $scannedAt) {
            Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $savedId = DB::table('mobile_attendance_requests')->where('employee_id', $employee->id)
                ->where('request_id', $data['request_id'])->value('attendance_id');
            if ($savedId) {
                $existing = Attendance::where('employee_id', $employee->id)->find($savedId);
                abort_unless($existing, 409, 'This attendance was corrected or removed. Refresh your history.');

                return [$existing, true];
            }
            $attendance = AttendanceCaptureService::capture([
                'employee_id' => $employee->id, 'time' => $scannedAt, 'workplace_id' => $unitId,
                'scan_latitude' => $data['latitude'], 'scan_longitude' => $data['longitude'],
                'attendance_source' => $token === '' ? 'api_gps' : 'api_qr', 'source_reference' => $reference,
            ]);
            DB::table('mobile_attendance_requests')->insert([
                'employee_id' => $employee->id, 'request_id' => $data['request_id'], 'attendance_id' => $attendance->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return [$attendance, ! $attendance->wasRecentlyCreated];
        });
        $workDate = $this->shifts->workDateForPunch($employee->id, Carbon::parse($attendance->time), (int) $attendance->machine_state);
        $logId = $this->log($request, $employee, ['status' => 'success', 'message' => 'Attendance saved.', 'meta_payload' => ['machine_state' => (int) $attendance->machine_state, 'attendance_id' => (int) $attendance->id]
            + ($offline ? ['offline' => true, 'captured_at' => $scannedAt->toIso8601String(), 'clock_source' => $request->input('clock_source', 'device')] : []),
            'range_meters' => $distance, 'acceptable_range_meters' => $radius, 'geofence_source' => $source]);

        return $this->ok([
            ...$geo, 'message' => $duplicate ? 'Attendance already saved.' : 'Attendance saved.',
            'attendance_id' => (int) $attendance->id, 'duplicate' => $duplicate, 'scan_log_id' => $logId,
            'machine_state' => (int) $attendance->machine_state, 'punch_type' => (int) $attendance->machine_state === 1 ? 'in' : 'out',
            'work_date' => $workDate->toDateString(), 'captured_at' => Carbon::parse($attendance->time)->toIso8601String(),
            'data' => $this->day($employee, $workDate),
            'meta' => ['timezone' => config('app.timezone'), 'next_punch_type' => AttendanceCaptureService::nextPunchType($employee->id, now())],
        ], $duplicate ? 200 : 201);
    }

    /**
     * The scan area the server enforces: the unit's own geofence (or its
     * location), else the global app setting. Also sent to the app so its
     * live in/out-of-zone indicator matches what scan() will decide.
     *
     * @return array{latitude: float, longitude: float, radius_meters: float, source: string}|null
     */
    private function geofence(?Department $unit): ?array
    {
        if (! $unit) {
            return null;
        }
        $settings = Appsetting::first();
        $lat = $unit->geofence_latitude ?? $unit->latitude;
        $lng = $unit->geofence_longitude ?? $unit->longitude;
        $source = 'workplace';
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            $lat = $settings?->latitude;
            $lng = $settings?->longitude;
            $source = 'global';
        }
        $radius = (float) ($unit->geofence_radius_meters ?: $settings?->acceptablerange);
        if (! is_numeric($lat) || ! is_numeric($lng) || $radius <= 0) {
            return null;
        }

        return ['latitude' => (float) $lat, 'longitude' => (float) $lng, 'radius_meters' => $radius, 'source' => $source];
    }

    private function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;

        return 6371000 * 2 * asin(sqrt(min(1, max(0, $a))));
    }

    private function scanError(Request $request, Employee $employee, string $code, string $message, int $status, array $extra = []): JsonResponse
    {
        $logId = $this->log($request, $employee, ['status' => 'error', 'error_code' => $code, 'message' => $message,
            'range_meters' => $extra['range'] ?? null, 'acceptable_range_meters' => $extra['acceptable_range'] ?? null,
            'geofence_source' => $extra['geofence_source'] ?? null]);

        return response()->json(['response' => ['status' => 'error', 'error_code' => $code, 'message' => $message, 'scan_log_id' => $logId, ...$extra]], $status);
    }

    public function reportIssue(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $data = $request->validate(['error_code' => ['required', 'string', 'max:80'], 'message' => ['required', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180']]);

        return $this->ok(['scan_log_id' => $this->log($request, $employee, ['status' => 'client_error', 'error_code' => $data['error_code'], 'message' => $data['message']])]);
    }

    private function log(Request $request, Employee $employee, array $data): ?int
    {
        try {
            return AttendanceScanLog::create($data + ['employee_id' => $employee->id, 'user_id' => $request->user()->id,
                'workplace_id' => AttendanceUnitScope::employeeUnit($employee) ?: null, 'scanned_at' => now(),
                'latitude' => $request->input('latitude'), 'longitude' => $request->input('longitude'),
                'request_ip' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ])->id;
        } catch (\Throwable $e) {
            Log::warning('mobile_attendance_scan_log_failed', ['exception' => get_class($e)]);

            return null;
        }
    }
}
