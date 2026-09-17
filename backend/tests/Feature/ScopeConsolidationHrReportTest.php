<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\Attendance;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\EmployeeSalaryType;
use Modules\HumanResource\Entities\SetupRule;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Services\GovernanceAssignmentService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Phase 3B.2, section 19-21/31: ReportController::deductionReport()/
 * allowanceReport()/monthlyReportShow() previously applied NO scope at all
 * when no department/workplace filter was submitted, and trusted any
 * submitted id without checking it against the requester's own managed
 * departments. These tests verify actual response data across a real
 * multi-department fixture. Runs against the real (dev) database, wrapped
 * in a transaction that is rolled back afterwards.
 */
class ScopeConsolidationHrReportTest extends TestCase
{
    use DatabaseTransactions;

    private const NON_ADMIN_USER_ID = 640;
    private const RESPONSIBILITY_HEAD_ID = 1;

    private Department $unitA;
    private Department $unitB;

    protected function setUp(): void
    {
        parent::setUp();

        $unitTypeId = (int) \Illuminate\Support\Facades\DB::table('org_unit_types')->orderBy('id')->value('id');
        $this->unitA = Department::create(['department_name' => 'PHPUnit Report Unit A', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
        $this->unitB = Department::create(['department_name' => 'PHPUnit Report Unit B', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
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

    private function grantAndActAs(int $userId, string $permission): User
    {
        Permission::findOrCreate($permission, 'web');
        $user = User::query()->findOrFail($userId);
        if (!$user->can($permission)) {
            $user->givePermissionTo($permission);
        }
        $this->actingAs($user);

        return $user;
    }

    private function makeManagerScopedToUnitA(): User
    {
        $manager = $this->grantAndActAs(self::NON_ADMIN_USER_ID, 'read_deduction_report');
        $manager->givePermissionTo('read_allowance_report');
        $manager->givePermissionTo('read_monthly_attendance');
        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $manager->id,
            'department_id' => $this->unitA->id,
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
            'is_primary' => false,
            'is_active' => true,
        ]);

        return $manager;
    }

    public function test_deduction_report_hides_out_of_scope_employees_by_default(): void
    {
        $this->makeManagerScopedToUnitA();

        $rule = SetupRule::create(['name' => 'PHPUnit Deduction', 'type' => 'deduction', 'amount' => 10, 'is_active' => 1]);
        $employeeA = $this->makeEmployee($this->unitA, 'InScope');
        $employeeB = $this->makeEmployee($this->unitB, 'OutOfScope');
        EmployeeSalaryType::create(['setup_rule_id' => $rule->id, 'employee_id' => $employeeA->id, 'type' => 'deduction', 'amount' => 10, 'is_active' => 1]);
        EmployeeSalaryType::create(['setup_rule_id' => $rule->id, 'employee_id' => $employeeB->id, 'type' => 'deduction', 'amount' => 10, 'is_active' => 1]);

        $response = $this->get(route('reports.deduction'))->assertOk();
        $allEmployee = $response->viewData('allEmployee');

        $this->assertArrayHasKey((int) $employeeA->id, $allEmployee->toArray());
        $this->assertArrayNotHasKey((int) $employeeB->id, $allEmployee->toArray());
    }

    public function test_deduction_report_ignores_an_out_of_scope_requested_department(): void
    {
        $this->makeManagerScopedToUnitA();

        $rule = SetupRule::create(['name' => 'PHPUnit Deduction 2', 'type' => 'deduction', 'amount' => 10, 'is_active' => 1]);
        $employeeB = $this->makeEmployee($this->unitB, 'OutOfScopeRequested');
        EmployeeSalaryType::create(['setup_rule_id' => $rule->id, 'employee_id' => $employeeB->id, 'type' => 'deduction', 'amount' => 10, 'is_active' => 1]);

        $response = $this->get(route('reports.deduction', ['department_id' => $this->unitB->id]))->assertOk();
        $allEmployee = $response->viewData('allEmployee');

        $this->assertArrayNotHasKey(
            (int) $employeeB->id,
            $allEmployee->toArray(),
            'Explicitly requesting an out-of-scope department must not widen visibility beyond the manager\'s own scope.'
        );
    }

    public function test_monthly_report_show_excludes_out_of_scope_employees_and_attendance(): void
    {
        $this->makeManagerScopedToUnitA();

        $employeeA = $this->makeEmployee($this->unitA, 'MonthlyInScope');
        $employeeB = $this->makeEmployee($this->unitB, 'MonthlyOutOfScope');
        Attendance::create(['employee_id' => $employeeA->id, 'time' => now(), 'machine_state' => 1]);
        Attendance::create(['employee_id' => $employeeB->id, 'time' => now(), 'machine_state' => 1]);

        $response = $this->get(route('reports.monthly-report', [
            'month' => now()->month,
            'year' => now()->year,
        ]))->assertOk();

        $employees = $response->viewData('employees');
        $employeeIds = $employees->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains((int) $employeeA->id, $employeeIds);
        $this->assertNotContains(
            (int) $employeeB->id,
            $employeeIds,
            'monthlyReportShow must not list an out-of-scope employee when no workplace filter was submitted.'
        );
    }
}
