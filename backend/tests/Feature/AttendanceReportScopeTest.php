<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\HumanResource\DataTables\AttendanceSummaryDataTable;
use Modules\HumanResource\DataTables\StaffAttendanceDataTable;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\ManualAttendance;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Services\GovernanceAssignmentService;
use Tests\TestCase;

/**
 * Attendance Management Phase A: StaffAttendanceDataTable::query() and
 * AttendanceSummaryDataTable::collection() only scoped their query when a
 * workplace_id/department_id filter was explicitly submitted -- with no
 * filter, both ran unscoped across every employee in the system. Fixed by
 * reusing OrgHierarchyAccessService::effectiveReportDepartmentIds(), the
 * same canonical helper ReportController's other reports already rely on.
 * These tests verify the ACTUAL ROWS RETURNED, not just a service's output,
 * matching the pattern established in ScopeConsolidationLeaveVisibilityTest.
 * Runs against the real (dev) database, wrapped in a transaction that is
 * rolled back afterwards.
 */
class AttendanceReportScopeTest extends TestCase
{
    use DatabaseTransactions;

    private const NON_ADMIN_USER_ID = 640;
    private const SUPER_ADMIN_USER_ID = 25;
    private const RESPONSIBILITY_HEAD_ID = 1;

    private Department $unitA;
    private Department $unitB;

    protected function setUp(): void
    {
        parent::setUp();

        $unitTypeId = (int) \Illuminate\Support\Facades\DB::table('org_unit_types')->orderBy('id')->value('id');
        $this->unitA = Department::create(['department_name' => 'PHPUnit Attendance Unit A', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
        $this->unitB = Department::create(['department_name' => 'PHPUnit Attendance Unit B', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
    }

    private function makeEmployee(Department $department, string $label): Employee
    {
        $employee = new Employee([
            'department_id' => $department->id,
            'first_name' => 'PHPUnit',
            'last_name' => $label,
            'is_active' => 1,
        ]);
        $employee->employee_id = 'PHPUNIT-' . Str::upper(Str::random(8));
        $employee->save();

        return $employee;
    }

    public function test_staff_attendance_report_restricts_to_managed_scope_with_no_filter_submitted(): void
    {
        $manager = User::query()->findOrFail(self::NON_ADMIN_USER_ID);
        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $manager->id,
            'department_id' => $this->unitA->id,
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
            'is_primary' => false,
            'is_active' => true,
        ]);

        $employeeA = $this->makeEmployee($this->unitA, 'InUnitA');
        $employeeB = $this->makeEmployee($this->unitB, 'InUnitB');

        $this->actingAs($manager);
        request()->merge([]); // no workplace_id / department_id submitted
        $visibleIds = app(StaffAttendanceDataTable::class)->query(new Employee())
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains((int) $employeeA->id, $visibleIds, 'Manager must see their own unit even with no filter submitted.');
        $this->assertNotContains((int) $employeeB->id, $visibleIds, 'Manager must NOT see an unrelated unit just because no filter was submitted.');
    }

    public function test_staff_attendance_report_cannot_be_widened_by_requesting_an_out_of_scope_workplace_id(): void
    {
        $manager = User::query()->findOrFail(self::NON_ADMIN_USER_ID);
        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $manager->id,
            'department_id' => $this->unitA->id,
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
            'is_primary' => false,
            'is_active' => true,
        ]);

        $employeeB = $this->makeEmployee($this->unitB, 'OutOfScope');

        $this->actingAs($manager);
        request()->merge(['workplace_id' => $this->unitB->id]);
        $visibleIds = app(StaffAttendanceDataTable::class)->query(new Employee())
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertNotContains(
            (int) $employeeB->id,
            $visibleIds,
            'Explicitly requesting an out-of-scope workplace_id must not bypass the manager\'s real scope.'
        );
    }

    public function test_staff_attendance_report_super_admin_sees_all_units_with_no_filter(): void
    {
        $admin = User::query()->findOrFail(self::SUPER_ADMIN_USER_ID);
        $employeeA = $this->makeEmployee($this->unitA, 'ForAdminA');
        $employeeB = $this->makeEmployee($this->unitB, 'ForAdminB');

        $this->actingAs($admin);
        request()->merge([]);
        $visibleIds = app(StaffAttendanceDataTable::class)->query(new Employee())
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains((int) $employeeA->id, $visibleIds);
        $this->assertContains((int) $employeeB->id, $visibleIds);
    }

    public function test_attendance_summary_report_restricts_active_employee_count_to_managed_scope_with_no_filter(): void
    {
        $manager = User::query()->findOrFail(self::NON_ADMIN_USER_ID);
        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $manager->id,
            'department_id' => $this->unitA->id,
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
            'is_primary' => false,
            'is_active' => true,
        ]);

        $this->makeEmployee($this->unitA, 'SummaryInScope');
        $employeeB = $this->makeEmployee($this->unitB, 'SummaryOutOfScope');
        ManualAttendance::query()->create([
            'employee_id' => $employeeB->id,
            'time' => now(),
            'machine_state' => 1,
        ]);

        $this->actingAs($manager);
        request()->merge([]); // default range (start of month .. today) already includes today
        $rows = collect(app(AttendanceSummaryDataTable::class)->collection());
        $today = $rows->firstWhere('date', now()->toDateString());

        // The out-of-scope employee's punch must not count toward this
        // manager's present-employee total, and must not count toward
        // "absent" for a colleague outside their scope either -- the whole
        // active-employee denominator must be confined to unitA.
        $this->assertNotNull($today);
        $this->assertLessThanOrEqual(1, $today['totalPresentEmployees'] + $today['totalAbsentEmployees'] + $today['totalLeaveEmployees']);
    }
}
