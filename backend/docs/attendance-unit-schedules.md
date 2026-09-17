# Attendance schedules by organization unit

Open **Attendance → ម៉ោងធ្វើការ** (`/hr/shifts`), select a unit, and enter its actual working hours. No official hours or staff duty assignments are seeded automatically.

- A split day uses four times: morning IN, morning OUT, afternoon IN, afternoon OUT. All four must be ordered within the same day. Each session requires its own IN/OUT pair.
- A continuous shift uses start/end only. Enable overnight for a shift ending the following day (up to 24 hours).
- Each unit can have one active regular default. Duty shifts require an explicit employee/date roster and cannot be unit defaults.
- Late and early departure grace periods are inclusive. Beyond grace, the full difference from the scheduled time is reported. Early arrival is reported separately. Work minutes count valid pairs within scheduled hours and exclude lunch, breaks, and time outside the schedule.
- The roster takes precedence over assignments, employee defaults, and unit defaults. Explicit roster work overrides weekends/public holidays; explicit roster OFF/holiday overrides regular schedules. Approved leave and missions remain excused.
- An incomplete pair, duplicate IN direction, or missing morning/afternoon session is incomplete. Late arrival and early departure minutes are both retained even when the daily label shows Late.

Use **តារាងវេនយាម** (`/hr/shift-rosters`) to set one day or a range of at most 31 days. Click a calendar cell to edit. Contradictory work/OFF/holiday choices, inactive or foreign-unit shifts, and adjacent overlapping shifts are rejected. Used schedules cannot be deleted or have their timing changed; create a replacement and update the intended roster dates.

Attendance units are provincial health department headquarters (`phd`), provincial hospitals, operational districts, health centers (including with/without beds), and health posts. Offices, sections, programs, and OD sections belong to their nearest containing attendance unit. Start from the employee's sub-department when present, otherwise department, and walk internal parents only. A child health center/post remains separate; unsupported types and broken/cyclic hierarchies are not silently assigned to a parent. Dropdowns and APIs show only permitted attendance units, and employee selection also retains branch permissions. The active organization assignments determine accessible units; an empty scope grants no access. Permission checks and unit restrictions apply to the web and v1 controller endpoints, including summary regeneration. Administrators can manage multiple units but select one unit in each web view.

An overnight checkout is attributed to the shift's start date. The boundary with the next shift is the midpoint between the previous end and next start; without a next schedule, the window closes four hours after scheduled end. OUT exactly at a shared boundary belongs to the previous overnight shift. Extremely late or incorrectly directed scans need the existing attendance correction workflow. Legacy rows without direction are paired in order. Legacy shifts without a unit remain available for existing explicit assignments; create unit schedules to replace them deliberately.

The daily page recalculates current summaries; roster changes and captures invalidate affected cached summaries. Existing attendance punches are preserved. Historical recalculation uses the currently configured unit default for dates without explicit rosters/assignments; use dated rosters/assignments when schedules change and historical policy must remain fixed.

## Installation and verification

Migration (already applied to the local `hrmdb` database during this change):

```powershell
php artisan migrate --path=modules/HumanResource/Database/Migrations/2026_09_12_090000_add_unit_attendance_schedules.php --force
```

Focused tests use an isolated in-memory SQLite database, with minimal schemas. From `backend`:

```powershell
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
php vendor/phpunit/phpunit/phpunit --filter 'AttendanceUnitScheduleTest|AttendanceSessionTest'
```

The v1 shift-create contract now requires `department_id`; roster clients may omit it to infer the authorized employee's unit. Bulk regeneration is limited to 31 days and to authorized employees. The existing v1 smoke fixture was updated to supply the unit and roll back its writes.
