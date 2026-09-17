# Mobile attendance API and management dashboard

Base path: `/api/v1/attendance`. Send `Accept: application/json` and `Authorization: Bearer <Sanctum token>`. JSON POST requests also use `Content-Type: application/json`.

Self-service endpoints resolve the active employee from the token's user. An officer does not need attendance-management permissions to see their own records. `employee_id`, `user_id`, `department_id`, and `workplace_id` are prohibited on these endpoints.

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/today` | Current work date, sessions, roster/default shift, unit, computed attendance, and next IN/OUT action |
| GET | `/history?from_date=2026-09-01&to_date=2026-09-30` | Personal daily records, newest first; future dates are omitted |
| GET | `/schedule?from_date=2026-09-01&to_date=2026-09-30` | Personal resolved schedules, including future duties, OFF/holidays, leave and missions |
| POST | `/scan` | QR/GPS capture using server time and inferred IN/OUT direction |
| POST | `/scan-issues` | Record a client scan problem against the authenticated employee |

Ranges are inclusive, at most 31 days. Missing dates default to the current month. A supplied `from_date` without `to_date` defaults to the end of that month. Invalid dates/ranges receive HTTP 422.

## Scan request

```json
{
  "request_id": "680c66f5-cf1b-48e7-a4bc-0322186a1509",
  "latitude": 11.55,
  "longitude": 104.92,
  "qr_token": "<signed attendance QR token, when required>"
}
```

Generate one UUID per intended scan and reuse it for transport retries. Every accepted request ID is stored, including IDs deduplicated against a nearby scan. Same-employee captures are serialized. QR and GPS rescans from the same source within 60 seconds return the existing punch without toggling direction. A repeated successful request returns HTTP 200 with `duplicate: true`; a new capture returns HTTP 201. Removed/corrected records can return HTTP 409 on replay.

Client `datetime` and `machine_state` are prohibited. Server time determines attendance; correction requests must use the existing adjustment workflow. QR tokens must belong to the employee's unit and pass signature/expiry checks. GPS capture without a QR remains available when `humanresource.attendance.require_qr_token` is false. Coordinates/radius prefer the unit's geofence configuration and otherwise use the existing global configuration. QR/GPS are validated again on retries.

The response keeps scan fields such as `message`, `range`, `acceptable_range`, `workplace_name`, `machine_state`, `punch_type`, `scan_log_id`, and adds `attendance_id`, `duplicate`, `captured_at`, `work_date`, and the recalculated daily record in `data`.

## Response contract

Successful responses use the mobile app's existing envelope:

```json
{
  "response": {
    "status": "ok",
    "data": {
      "date": "2026-09-08",
      "employee_id": 7,
      "unit": {"id": 1, "name": "Unit A"},
      "attendance_status": "late_and_early_leave",
      "is_provisional": true,
      "in_time": "2026-09-08 08:00:00",
      "out_time": "2026-09-08 16:50:00",
      "worked_minutes": 390,
      "total_hours": "6:30:00",
      "late_minutes": 20,
      "early_leave_minutes": 10,
      "early_arrival_minutes": 0,
      "punch_count": 4,
      "has_exception": false,
      "exception_reason": null,
      "shift_source": "unit_default",
      "is_day_off": false,
      "is_holiday": false,
      "shift": {"id": 1, "name": "Regular", "is_duty": false, "is_cross_day": false},
      "sessions": []
    },
    "meta": {"timezone": "Asia/Phnom_Penh", "next_punch_type": "in"}
  }
}
```

The example omits session entries and some shift fields for brevity. Each session includes `name` (`morning`, `afternoon`, `duty`, `work`), `scheduled_in`, `scheduled_out`, `in_time`, `out_time`, `worked_minutes`, `late_minutes`, `early_leave_minutes`, `early_arrival_minutes`, `complete`, and `punch_count`. Nullable timestamps mean no recorded punch, not midnight. Use `meta.timezone` for local schedule timestamps; `captured_at` and `server_time` include an ISO offset.

History and schedule use an array in `response.data`. Today also supplies `meta.server_time`. Today can refer to yesterday's work date during an overnight duty. Use `meta.next_punch_type`, not calendar-day punch parity.

Status codes: `on_time`, `late`, `early_leave`, `late_and_early_leave`, `incomplete`, `absent`, `leave`, `mission`, `day_off`, `holiday`. `is_provisional` marks a current/unclosed work date; do not treat its absence/incomplete status as final. Work minutes, including zero, are authoritative: never substitute first-to-last elapsed time, which includes lunch or missing punches.

Scan failures use HTTP 403/422 and `response.status: error`, with `error_code`, `message`, and optional distance/log fields. Codes include `workplace_not_found`, `wrong_workplace`, `qr_token_required`, `invalid_qr`, `geofence_not_configured`, and `out_of_range`. Laravel input validation uses standard HTTP 422 `{message, errors}`. Authentication uses HTTP 401; throttling uses HTTP 429. Scan and issue-report endpoints allow 30 requests per minute per authenticated user.

## Management dashboard

Web entry: `/hr/attendances/workflow` (existing overview link). Approval/device setup remains at `/hr/attendances/settings`.

| Method | Path | Contract |
| --- | --- | --- |
| GET | `/units` | Units visible through the user's effective organization assignments; requires `read_attendance` |
| GET | `/dashboard?department_id=1&date=2026-09-08` | Requires `read_attendance` and `attendance_management`; explicit unit selection required |

Dashboard `response.data` contains `date`, `unit`, `summary`, `sessions`, and a paginated `records` object (20 officers per page). Filters: `status=all|recorded|attention|late|early_leave|absent|incomplete|duty|waiting|unscheduled|leave|mission|off`, `q` for officer name/number, and `page`. Summary counts cover the entire selected unit/date, independent of table filters. Attention counts distinct officers, so one late-and-early-leaving officer counts once. Open sessions use Waiting/In Progress; a missed closed morning session becomes Incomplete until the full day ends. Leave/mission/OFF are excused; officers without a resolved shift are Unscheduled.

Management shift/roster endpoints under `/api/v1/shifts` and `/api/v1/shift-rosters` remain permission- and unit-scoped. Added `PUT /shifts/{id}`, `DELETE /shifts/{id}`, and `DELETE /shift-rosters/{id}`. Shift creation requires `department_id`.

## Deployment and validation

Apply the schedule migration from `attendance-unit-schedules.md`, followed by:

```powershell
php artisan migrate --path=modules/HumanResource/Database/Migrations/2026_09_12_100000_create_mobile_attendance_requests.php --force
php artisan view:cache
```

The Flutter attendance service now uses these authenticated endpoints. History details show session schedules, actual IN/OUT, early arrival, lateness, and early departure. Rebuild/update the app to distribute this change. Older legacy endpoint routes remain for compatibility; the updated Flutter attendance service does not fall back to them.

Tests use in-memory SQLite (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`): PHPUnit filters `AttendanceDashboardTest|MobileAttendanceApiTest|AttendanceUnitScheduleTest|AttendanceSessionTest`. Flutter contract tests: `flutter test test/attendance_api_test.dart`.
