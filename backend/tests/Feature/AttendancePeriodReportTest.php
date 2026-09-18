<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\AttendanceDailySnapshot;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Shift;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Services\GovernanceAssignmentService;
use Modules\HumanResource\Services\PeriodAttendanceSummaryService;
use Tests\TestCase;

/**
 * Attendance Management Phase E: week/quarter/semester/year attendance +
 * duty-hour reports, built on the shared PeriodAttendanceSummaryService.
 * Runs against the real (dev) database, wrapped in a transaction that is
 * rolled back afterwards.
 */
class AttendancePeriodReportTest extends TestCase
{
    use DatabaseTransactions;

    private const SUPER_ADMIN_ID = 25;
    private const NON_ADMIN_ID = 640;
    private const RESPONSIBILITY_HEAD_ID = 1;

    private Department $unitA;
    private Department $unitB;

    protected function setUp(): void
    {
        parent::setUp();
        $unitTypeId = (int) \Illuminate\Support\Facades\DB::table('org_unit_types')->orderBy('id')->value('id');
        $this->unitA = Department::create(['department_name' => 'PHPUnit Period Report Unit A', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
        $this->unitB = Department::create(['department_name' => 'PHPUnit Period Report Unit B', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
    }

    private function admin(): User
    {
        return User::query()->findOrFail(self::SUPER_ADMIN_ID);
    }

    private function makeEmployee(Department $department, string $label): Employee
    {
        $employee = new Employee(['department_id' => $department->id, 'first_name' => 'PHPUnit', 'last_name' => $label, 'is_active' => 1]);
        $employee->employee_id = 'PHPUNIT-' . Str::upper(Str::random(8));
        $employee->save();

        return $employee;
    }

    public function test_summary_service_splits_duty_and_regular_hours_and_counts_statuses(): void
    {
        $employee = $this->makeEmployee($this->unitA, 'Summary');
        $dutyShift = Shift::create(['uuid' => (string) Str::uuid(), 'department_id' => $this->unitA->id, 'name' => 'Duty', 'start_time' => '18:00', 'end_time' => '06:00', 'is_cross_day' => true, 'is_duty' => true, 'is_active' => true]);
        $regularShift = Shift::create(['uuid' => (string) Str::uuid(), 'department_id' => $this->unitA->id, 'name' => 'Regular', 'start_time' => '07:00', 'end_time' => '16:00', 'is_active' => true]);

        $from = now()->startOfMonth();
        AttendanceDailySnapshot::create(['employee_id' => $employee->id, 'snapshot_date' => $from->toDateString(), 'shift_id' => $dutyShift->id, 'attendance_status' => 'Present', 'worked_minutes' => 600]);
        AttendanceDailySnapshot::create(['employee_id' => $employee->id, 'snapshot_date' => $from->copy()->addDay()->toDateString(), 'shift_id' => $regularShift->id, 'attendance_status' => 'Present', 'worked_minutes' => 480]);
        AttendanceDailySnapshot::create(['employee_id' => $employee->id, 'snapshot_date' => $from->copy()->addDays(2)->toDateString(), 'shift_id' => null, 'attendance_status' => 'Absent', 'worked_minutes' => 0]);
        AttendanceDailySnapshot::create(['employee_id' => $employee->id, 'snapshot_date' => $from->copy()->addDays(3)->toDateString(), 'shift_id' => null, 'attendance_status' => 'On Leave', 'worked_minutes' => 0]);

        $rows = app(PeriodAttendanceSummaryService::class)->summarize([$this->unitA->id], $from, $from->copy()->addDays(3));
        $row = $rows->firstWhere('employee_id', $employee->id);

        $this->assertSame(2, $row['present_days']);
        $this->assertSame(1, $row['absent_days']);
        $this->assertSame(1, $row['leave_days']);
        $this->assertSame(10.0, $row['duty_hours']);
        $this->assertSame(8.0, $row['regular_hours']);
    }

    public function test_summary_service_respects_department_scope(): void
    {
        $employeeA = $this->makeEmployee($this->unitA, 'InScope');
        $employeeB = $this->makeEmployee($this->unitB, 'OutOfScope');
        AttendanceDailySnapshot::create(['employee_id' => $employeeA->id, 'snapshot_date' => now()->toDateString(), 'attendance_status' => 'Present', 'worked_minutes' => 480]);
        AttendanceDailySnapshot::create(['employee_id' => $employeeB->id, 'snapshot_date' => now()->toDateString(), 'attendance_status' => 'Present', 'worked_minutes' => 480]);

        $rows = app(PeriodAttendanceSummaryService::class)->summarize([$this->unitA->id], now()->startOfMonth(), now()->endOfMonth());

        $this->assertTrue($rows->contains('employee_id', $employeeA->id));
        $this->assertFalse($rows->contains('employee_id', $employeeB->id));
    }

    public function test_weekly_report_route_restricts_to_managed_scope_with_no_filter(): void
    {
        $manager = User::query()->findOrFail(self::NON_ADMIN_ID);
        if (! $manager->can('read_attendance_report')) {
            $manager->givePermissionTo('read_attendance_report');
        }
        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $manager->id,
            'department_id' => $this->unitA->id,
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
            'is_primary' => false,
            'is_active' => true,
        ]);
        $employeeA = $this->makeEmployee($this->unitA, 'WeeklyInScope');
        $employeeB = $this->makeEmployee($this->unitB, 'WeeklyOutOfScope');
        AttendanceDailySnapshot::create(['employee_id' => $employeeA->id, 'snapshot_date' => now()->toDateString(), 'attendance_status' => 'Present', 'worked_minutes' => 480]);
        AttendanceDailySnapshot::create(['employee_id' => $employeeB->id, 'snapshot_date' => now()->toDateString(), 'attendance_status' => 'Present', 'worked_minutes' => 480]);

        $response = $this->actingAs($manager)->getJson(route('reports.attendance-weekly'));
        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('employee_id');

        $this->assertContains($employeeA->id, $ids);
        $this->assertNotContains($employeeB->id, $ids);
    }

    public function test_all_four_period_reports_render_for_super_admin(): void
    {
        $admin = $this->admin();
        foreach (['reports.attendance-weekly', 'reports.attendance-quarterly', 'reports.attendance-semester', 'reports.attendance-yearly'] as $routeName) {
            $this->actingAs($admin)->get(route($routeName))->assertOk();
        }
    }
}
