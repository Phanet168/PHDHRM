<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\HumanResource\DataTables\LeaveApplicationDataTable;
use Modules\HumanResource\Entities\ApplyLeave;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\LeaveType;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Services\GovernanceAssignmentService;
use Tests\TestCase;

/**
 * Phase 3B.2, section 16-17/28-29: LeaveApplicationDataTable::query() was
 * previously completely unscoped (serverSide(false) sends every row to the
 * browser; only the index() controller's dropdown hid rows client-side).
 * These tests verify the ACTUAL ROWS RETURNED by the query, not just a
 * service's output, across a real multi-department fixture. Runs against
 * the real (dev) database, wrapped in a transaction that is rolled back
 * afterwards.
 */
class ScopeConsolidationLeaveVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    private const NON_ADMIN_USER_ID = 640;
    private const SUPER_ADMIN_USER_ID = 25;
    private const RESPONSIBILITY_HEAD_ID = 1;

    private Department $unitA;
    private Department $unitAChild;
    private Department $unitB;

    protected function setUp(): void
    {
        parent::setUp();

        $unitTypeId = (int) \Illuminate\Support\Facades\DB::table('org_unit_types')->orderBy('id')->value('id');

        $this->unitA = Department::create(['department_name' => 'PHPUnit Leave Unit A', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
        $this->unitAChild = Department::create(['department_name' => 'PHPUnit Leave Unit A Child', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->unitA->id, 'is_active' => true]);
        $this->unitB = Department::create(['department_name' => 'PHPUnit Leave Unit B', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
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

    private function makeLeaveFor(Employee $employee): ApplyLeave
    {
        $leaveType = LeaveType::query()->create([
            'uuid' => (string) Str::uuid(),
            'leave_type' => 'PHPUnit Leave Type ' . Str::random(6),
            'leave_days' => 5,
            'leave_code' => 'PU-' . Str::upper(Str::random(4)),
        ]);

        return ApplyLeave::query()->create([
            'uuid' => (string) Str::uuid(),
            'employee_id' => (int) $employee->id,
            'leave_type_id' => (int) $leaveType->id,
            'leave_apply_start_date' => now()->toDateString(),
            'leave_apply_end_date' => now()->toDateString(),
            'leave_apply_date' => now()->toDateString(),
            'total_apply_day' => 1,
            'reason' => 'PHPUnit scope visibility test',
            'is_approved_by_manager' => 0,
            'is_approved' => 0,
            'workflow_status' => 'pending',
        ]);
    }

    public function test_unit_tree_manager_sees_own_unit_and_child_but_not_sibling_unit(): void
    {
        $manager = User::query()->findOrFail(self::NON_ADMIN_USER_ID);
        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $manager->id,
            'department_id' => $this->unitA->id,
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
            'is_primary' => false,
            'is_active' => true,
        ]);

        $employeeA = $this->makeEmployee($this->unitA, 'InUnitA');
        $employeeAChild = $this->makeEmployee($this->unitAChild, 'InUnitAChild');
        $employeeB = $this->makeEmployee($this->unitB, 'InUnitB');

        $leaveA = $this->makeLeaveFor($employeeA);
        $leaveAChild = $this->makeLeaveFor($employeeAChild);
        $leaveB = $this->makeLeaveFor($employeeB);

        $this->actingAs($manager);
        $visibleIds = app(LeaveApplicationDataTable::class)->query(new ApplyLeave())
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains((int) $leaveA->id, $visibleIds, 'Manager must see their own unit.');
        $this->assertContains((int) $leaveAChild->id, $visibleIds, 'UNIT_TREE (self_and_children) must include the child unit.');
        $this->assertNotContains((int) $leaveB->id, $visibleIds, 'Manager must NOT see an unrelated sibling unit.');
    }

    public function test_employee_with_no_management_scope_sees_only_own_leave(): void
    {
        $plainUser = User::query()->findOrFail(self::NON_ADMIN_USER_ID);

        // Reuse this user's REAL, already-linked employee record (rather
        // than creating a second employee row for the same user_id, which
        // User::employee() a hasOne relation would not reliably resolve to)
        // and just move it into the throwaway unit for this test -- rolled
        // back automatically by DatabaseTransactions.
        $ownEmployee = $plainUser->employee()->firstOrFail();
        $ownEmployee->department_id = $this->unitA->id;
        $ownEmployee->sub_department_id = null;
        $ownEmployee->save();

        // This user has zero UserAssignment rows in this test's transaction,
        // so managedBranchIds() must return [] and they should fall back to
        // strictly their own employee_id.
        $ownLeave = $this->makeLeaveFor($ownEmployee);

        $colleagueEmployee = $this->makeEmployee($this->unitA, 'SameUnitColleague');
        $colleagueLeave = $this->makeLeaveFor($colleagueEmployee);

        $this->actingAs($plainUser);
        $visibleIds = app(LeaveApplicationDataTable::class)->query(new ApplyLeave())
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains((int) $ownLeave->id, $visibleIds, 'Must always see own leave.');
        $this->assertNotContains(
            (int) $colleagueLeave->id,
            $visibleIds,
            'A user with no management scope must not see a same-unit colleague\'s leave just by being in the same department.'
        );
    }

    public function test_super_admin_sees_all_units(): void
    {
        $admin = User::query()->findOrFail(self::SUPER_ADMIN_USER_ID);

        $employeeA = $this->makeEmployee($this->unitA, 'ForAdminA');
        $employeeB = $this->makeEmployee($this->unitB, 'ForAdminB');
        $leaveA = $this->makeLeaveFor($employeeA);
        $leaveB = $this->makeLeaveFor($employeeB);

        $this->actingAs($admin);
        $visibleIds = app(LeaveApplicationDataTable::class)->query(new ApplyLeave())
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains((int) $leaveA->id, $visibleIds);
        $this->assertContains((int) $leaveB->id, $visibleIds);
    }

    public function test_employee_id_param_can_only_narrow_never_widen_visibility(): void
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
        $leaveB = $this->makeLeaveFor($employeeB);

        $this->actingAs($manager);
        request()->merge(['employee_id' => $employeeB->id]);
        $visibleIds = app(LeaveApplicationDataTable::class)->query(new ApplyLeave())
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertNotContains(
            (int) $leaveB->id,
            $visibleIds,
            'Passing an out-of-scope employee_id must not bypass the server-side scope restriction.'
        );
    }
}
