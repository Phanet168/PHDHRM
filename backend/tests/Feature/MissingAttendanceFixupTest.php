<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\Attendance;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Shift;
use Modules\HumanResource\Entities\ShiftRoster;
use Tests\TestCase;

/**
 * The "missing attendance" quick-fix page (ការខកខានវត្តមាន) previously only
 * let an admin backfill a plain clock-in/clock-out pair for an employee with
 * no punches that day. Extended to also support marking the day off, a
 * public holiday, or assigning a specific duty shift -- reusing the same
 * ShiftRosterAssignmentService (and its overlap-check logic) that
 * shift-rosters/index.blade.php's own forms already rely on, instead of
 * duplicating that logic. Leave/mission are deliberately NOT offered here:
 * those statuses come from approved ApplyLeave/Mission records elsewhere,
 * and fabricating one from this quick-fix tool would bypass that approval
 * trail. Also verifies the employee list now renders grouped by office with
 * a ល.រ sequence number, matching the other attendance list pages. Runs
 * against the real (dev) database, wrapped in a transaction that is rolled
 * back afterwards.
 */
class MissingAttendanceFixupTest extends TestCase
{
    use DatabaseTransactions;

    private const SUPER_ADMIN_ID = 25;

    private Department $healthCenter;
    private Shift $dutyShift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $healthCenterTypeId = (int) DB::table('org_unit_types')->where('code', 'health_center')->value('id');
        $this->healthCenter = Department::create([
            'department_name' => 'PHPUnit Missing Attendance Health Center',
            'unit_type_id' => $healthCenterTypeId,
            'is_active' => true,
        ]);
        $this->dutyShift = Shift::create([
            'uuid' => (string) Str::uuid(),
            'department_id' => $this->healthCenter->id,
            'name' => 'PHPUnit Duty',
            'start_time' => '18:00',
            'end_time' => '06:00',
            'is_cross_day' => true,
            'is_duty' => true,
            'is_active' => true,
            'created_by' => self::SUPER_ADMIN_ID,
        ]);
    }

    private function admin(): User
    {
        return User::query()->findOrFail(self::SUPER_ADMIN_ID);
    }

    private function makeEmployee(string $label): Employee
    {
        $employee = new Employee([
            'department_id' => $this->healthCenter->id,
            'first_name' => 'PHPUnit',
            'last_name' => $label,
            'is_active' => 1,
        ]);
        $employee->employee_id = 'PHPUNIT-' . Str::upper(Str::random(8));
        $employee->save();

        return $employee;
    }

    public function test_page_renders_missing_employees_grouped_with_sequence_numbers(): void
    {
        $employee = $this->makeEmployee('MissingToday');
        $date = now()->toDateString();

        $response = $this->actingAs($this->admin())->get(route('attendances.missingAttendance', [
            'date' => $date,
            'department_id' => $this->healthCenter->id,
        ]));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertTrue(str_contains($content, 'PHPUnit Missing Attendance Health Center'));
        $this->assertTrue(str_contains($content, $employee->employee_id));
        $this->assertTrue(str_contains($content, 'row-type'));
    }

    public function test_the_page_defaults_to_one_managed_unit_instead_of_the_whole_scope(): void
    {
        // Regression guard: this page used to have no department filter at
        // all, so a province-wide role got every employee across every
        // facility in one page load (hundreds of Select2 widgets), making
        // the page painfully slow. It must now default to ONE unit, exactly
        // like every sibling attendance page (shift-rosters, shift-teams,
        // daily-snapshot, monthlycreate).
        $employee = $this->makeEmployee('DefaultScopeCheck');
        $date = now()->toDateString();

        $response = $this->actingAs($this->admin())->get(route('attendances.missingAttendance', ['date' => $date]));

        $response->assertOk();
        $this->assertTrue(str_contains($response->getContent(), 'attendance-unit'), 'Expected the unit-filter dropdown to be present.');
    }

    public function test_night_session_is_only_shown_for_employees_at_a_duty_eligible_facility(): void
    {
        $dutyStaff = $this->makeEmployee('DutyFacilityStaff');
        $date = now()->toDateString();

        $officeTypeId = (int) DB::table('org_unit_types')->where('code', 'phd')->value('id');
        $office = Department::create(['department_name' => 'PHPUnit Admin Office', 'unit_type_id' => $officeTypeId, 'is_active' => true]);
        $officeStaff = new Employee(['department_id' => $office->id, 'first_name' => 'PHPUnit', 'last_name' => 'OfficeStaff', 'is_active' => 1]);
        $officeStaff->employee_id = 'PHPUNIT-' . Str::upper(Str::random(8));
        $officeStaff->save();

        $dutyResponse = $this->actingAs($this->admin())->get(route('attendances.missingAttendance', [
            'date' => $date,
            'department_id' => $this->healthCenter->id,
        ]));
        $officeResponse = $this->actingAs($this->admin())->get(route('attendances.missingAttendance', [
            'date' => $date,
            'department_id' => $office->id,
        ]));
        $dutyResponse->assertOk();
        $officeResponse->assertOk();

        $extractRow = function (string $html, string $needle): string {
            $start = strpos($html, $needle);
            $this->assertNotFalse($start, "Could not find {$needle} in the response.");
            $end = strpos($html, '</tr>', $start);
            $this->assertNotFalse($end);

            return substr($html, $start, $end - $start);
        };
        $dutyRowHtml = $extractRow($dutyResponse->getContent(), (string) $dutyStaff->employee_id);
        $officeRowHtml = $extractRow($officeResponse->getContent(), (string) $officeStaff->employee_id);
        $this->assertTrue(str_contains($dutyRowHtml, 'data-session="night"'));
        $this->assertFalse(str_contains($officeRowHtml, 'data-session="night"'));
    }

    public function test_in_out_type_still_records_two_attendance_punches(): void
    {
        $employee = $this->makeEmployee('InOut');
        $date = now()->toDateString();

        $response = $this->actingAs($this->admin())->postJson(route('attendances.missingAttendance.store'), [
            'employee_id' => [$employee->id],
            'type' => ['in_out'],
            'morning_in' => ['08:00'],
            'morning_out' => ['12:00'],
            'afternoon_in' => ['13:00'],
            'afternoon_out' => ['17:00'],
            'shift_id' => [null],
            'date' => $date,
        ]);

        $response->assertOk();
        $this->assertSame(200, $response->json('status'));
        $this->assertSame(4, Attendance::where('employee_id', $employee->id)->whereDate('time', $date)->count());
    }

    public function test_in_out_type_records_an_overnight_duty_session(): void
    {
        $employee = $this->makeEmployee('OvernightDuty');
        $date = now()->toDateString();

        $response = $this->actingAs($this->admin())->postJson(route('attendances.missingAttendance.store'), [
            'employee_id' => [$employee->id],
            'type' => ['in_out'],
            'night_in' => ['20:00'],
            'night_out' => ['06:00'],
            'date' => $date,
        ]);

        $response->assertOk();
        $this->assertSame(200, $response->json('status'));
        $punches = Attendance::where('employee_id', $employee->id)->orderBy('time')->pluck('time');
        $this->assertCount(2, $punches);
        $this->assertTrue(\Carbon\Carbon::parse($punches->last())->greaterThan(\Carbon\Carbon::parse($punches->first())));
    }

    public function test_day_off_type_creates_a_day_off_roster_row(): void
    {
        $employee = $this->makeEmployee('DayOff');
        $date = now()->toDateString();

        $response = $this->actingAs($this->admin())->postJson(route('attendances.missingAttendance.store'), [
            'employee_id' => [$employee->id],
            'type' => ['day_off'],
            'date' => $date,
        ]);

        $response->assertOk();
        $this->assertSame(200, $response->json('status'));
        $row = ShiftRoster::where('employee_id', $employee->id)->whereDate('roster_date', $date)->first();
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->is_day_off);
        $this->assertFalse((bool) $row->is_holiday);
        $this->assertSame(0, Attendance::where('employee_id', $employee->id)->whereDate('time', $date)->count());
    }

    public function test_holiday_type_creates_a_holiday_roster_row(): void
    {
        $employee = $this->makeEmployee('Holiday');
        $date = now()->toDateString();

        $response = $this->actingAs($this->admin())->postJson(route('attendances.missingAttendance.store'), [
            'employee_id' => [$employee->id],
            'type' => ['holiday'],
            'date' => $date,
        ]);

        $response->assertOk();
        $row = ShiftRoster::where('employee_id', $employee->id)->whereDate('roster_date', $date)->first();
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->is_holiday);
    }

    public function test_shift_type_assigns_the_chosen_duty_shift(): void
    {
        $employee = $this->makeEmployee('ShiftAssign');
        $date = now()->toDateString();

        $response = $this->actingAs($this->admin())->postJson(route('attendances.missingAttendance.store'), [
            'employee_id' => [$employee->id],
            'type' => ['shift'],
            'shift_id' => [$this->dutyShift->id],
            'date' => $date,
        ]);

        $response->assertOk();
        $row = ShiftRoster::where('employee_id', $employee->id)->whereDate('roster_date', $date)->first();
        $this->assertNotNull($row);
        $this->assertSame($this->dutyShift->id, $row->shift_id);
    }

    public function test_in_out_type_without_times_is_rejected(): void
    {
        $employee = $this->makeEmployee('MissingTimes');

        $response = $this->actingAs($this->admin())->postJson(route('attendances.missingAttendance.store'), [
            'employee_id' => [$employee->id],
            'type' => ['in_out'],
            'date' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
    }

    public function test_shift_type_without_shift_id_is_rejected(): void
    {
        $employee = $this->makeEmployee('MissingShiftId');

        $response = $this->actingAs($this->admin())->postJson(route('attendances.missingAttendance.store'), [
            'employee_id' => [$employee->id],
            'type' => ['shift'],
            'date' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
    }

    public function test_in_out_type_with_one_sided_session_time_is_rejected(): void
    {
        $employee = $this->makeEmployee('OneSidedSession');

        $response = $this->actingAs($this->admin())->postJson(route('attendances.missingAttendance.store'), [
            'employee_id' => [$employee->id],
            'type' => ['in_out'],
            'morning_in' => ['08:00'],
            'date' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, Attendance::where('employee_id', $employee->id)->count());
    }
}
