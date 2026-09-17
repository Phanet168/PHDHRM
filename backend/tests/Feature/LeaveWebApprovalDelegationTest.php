<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\ApplyLeave;
use Modules\HumanResource\Entities\Delegation;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\LeaveType;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Entities\WorkflowDefinition;
use Modules\HumanResource\Entities\WorkflowDefinitionStep;
use Modules\HumanResource\Entities\WorkflowInstance;
use Modules\HumanResource\Services\DelegationService;
use Modules\HumanResource\Services\GovernanceAssignmentService;
use Tests\TestCase;

/**
 * Phase 3D.2, section 5/7: proves the REAL Leave Web approval path
 * (PUT leave.approved-by-manager -> LeaveController::ApprovedByManager ->
 * applyWorkflowApprovalDecision -> canUserActOnWorkflowStep) is
 * delegation-aware after being migrated onto
 * AccessControlService::canApprove(), not just AccessControlService in
 * isolation. Runs against the real (dev) database, wrapped in a
 * transaction that is rolled back afterwards.
 */
class LeaveWebApprovalDelegationTest extends TestCase
{
    use DatabaseTransactions;

    private const RESPONSIBILITY_HEAD_ID = 1;
    private const DIRECTOR_ID = 640;
    private const DEPUTY_ID = 26;

    private Department $phd;
    private Department $odA;
    private Department $hcA1;
    private Department $odB;
    private Department $hcB1;

    protected function setUp(): void
    {
        parent::setUp();

        $unitTypeId = (int) \Illuminate\Support\Facades\DB::table('org_unit_types')->orderBy('id')->value('id');
        $this->phd = Department::create(['department_name' => 'PHPUnit LeaveWeb PHD', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
        $this->odA = Department::create(['department_name' => 'PHPUnit LeaveWeb OD A', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->phd->id, 'is_active' => true]);
        $this->hcA1 = Department::create(['department_name' => 'PHPUnit LeaveWeb HC A1', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->odA->id, 'is_active' => true]);
        $this->odB = Department::create(['department_name' => 'PHPUnit LeaveWeb OD B', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->phd->id, 'is_active' => true]);
        $this->hcB1 = Department::create(['department_name' => 'PHPUnit LeaveWeb HC B1', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->odB->id, 'is_active' => true]);
    }

    private function director(): User
    {
        $user = User::query()->findOrFail(self::DIRECTOR_ID);
        $user->givePermissionTo('create_leave_approval');

        return $user;
    }

    private function deputy(): User
    {
        $user = User::query()->findOrFail(self::DEPUTY_ID);
        $user->givePermissionTo('create_leave_approval');

        return $user;
    }

    private function giveDirectorNativeLeaveApprovalAuthority(): void
    {
        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => self::DIRECTOR_ID,
            'department_id' => $this->phd->id,
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_ALL,
            'is_primary' => false,
            'is_active' => true,
        ]);
    }

    private function delegateLeaveToDeputy(array $overrides = []): Delegation
    {
        return app(DelegationService::class)->create(array_merge([
            'delegator_user_id' => self::DIRECTOR_ID,
            'delegatee_user_id' => self::DEPUTY_ID,
            'authority_module_key' => 'leave',
            'scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
            'scope_department_ids' => [$this->odA->id],
            'starts_at' => now()->subHour()->toDateTimeString(),
            'ends_at' => now()->addDay()->toDateTimeString(),
        ], $overrides));
    }

    /**
     * Gives Director native authority over a second, unrelated module (via
     * an isolated throwaway definition, so this never touches a real
     * production module) so a delegation can legitimately be created for
     * that module -- DelegationService::create() rejects delegating an
     * authority the delegator does not natively hold, so an unrelated real
     * module key cannot be used here unless Director happens to already
     * hold it in production data.
     */
    private function makeUnrelatedModuleDelegatable(): void
    {
        $definition = WorkflowDefinition::create([
            'module_key' => 'phpunit_leaveweb_other_module',
            'request_type_key' => 'test_type',
            'name' => 'PHPUnit LeaveWeb Other Module',
            'priority' => 999,
            'is_active' => true,
        ]);
        $definition->steps()->create([
            'step_order' => 1,
            'step_name' => 'PHPUnit Other Approval',
            'action_type' => 'approve',
            'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY,
            'actor_responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
            'system_role_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_ALL,
            'is_final_approval' => true,
            'is_required' => true,
            'can_return' => true,
            'can_reject' => true,
        ]);
    }

    /**
     * @param int|null $requesterUserId when set, links the leave's employee
     *   to this user id so isRequesterUser() self-approval checks apply.
     */
    private function makeLeaveIn(Department $department, ?int $requesterUserId = null): ApplyLeave
    {
        $employee = new Employee([
            'department_id' => $department->id,
            'user_id' => $requesterUserId,
            'first_name' => 'PHPUnit',
            'last_name' => 'LeaveWebSubject',
            'is_active' => 1,
        ]);
        $employee->employee_id = 'PHPUNIT-' . Str::upper(Str::random(8));
        $employee->save();

        $leaveType = LeaveType::query()->create([
            'uuid' => (string) Str::uuid(),
            'leave_type' => 'PHPUnit LeaveWeb Type ' . Str::random(6),
            'leave_days' => 5,
            'leave_code' => 'PU-' . Str::upper(Str::random(4)),
        ]);

        $leave = ApplyLeave::query()->create([
            'uuid' => (string) Str::uuid(),
            'employee_id' => (int) $employee->id,
            'leave_type_id' => (int) $leaveType->id,
            'leave_apply_start_date' => now()->toDateString(),
            'leave_apply_end_date' => now()->toDateString(),
            'leave_apply_date' => now()->toDateString(),
            'total_apply_day' => 1,
            'reason' => 'PHPUnit leave web delegation test',
            'is_approved_by_manager' => 0,
            'is_approved' => 0,
            'workflow_status' => 'pending',
        ]);

        $definition = WorkflowDefinition::create([
            'module_key' => 'leave',
            'request_type_key' => 'leave_request',
            'name' => 'PHPUnit LeaveWeb Definition',
            'priority' => 999,
            'is_active' => true,
        ]);
        $definition->steps()->create([
            'step_order' => 1,
            'step_name' => 'PHPUnit Leave Approval',
            'action_type' => 'approve',
            'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY,
            'actor_responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
            'system_role_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
            'is_final_approval' => true,
            'is_required' => true,
            'can_return' => true,
            'can_reject' => true,
        ]);

        $instance = WorkflowInstance::create([
            'uuid' => (string) Str::uuid(),
            'module_key' => 'leave',
            'request_type_key' => 'leave_request',
            'source_type' => ApplyLeave::class,
            'source_id' => (int) $leave->id,
            'workflow_definition_id' => (int) $definition->id,
            'status' => 'pending',
            'current_step_order' => 1,
            'submitted_by' => (int) ($requesterUserId ?: self::DIRECTOR_ID),
            'submitted_at' => now(),
        ]);

        $leave->update([
            'workflow_instance_id' => (int) $instance->id,
            'workflow_status' => 'pending',
            'workflow_current_step_order' => 1,
        ]);

        return $leave->fresh();
    }

    private function approveViaWebRoute(User $actor, ApplyLeave $leave): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($actor)->put(route('leave.approved-by-manager', $leave->uuid), [
            'leave_approved_start_date' => $leave->leave_apply_start_date,
            'leave_approved_end_date' => $leave->leave_apply_end_date,
            'total_approved_day' => 1,
            'description' => 'PHPUnit decision',
        ]);
    }

    public function test_native_approver_can_approve_via_real_web_route(): void
    {
        $this->giveDirectorNativeLeaveApprovalAuthority();
        $leave = $this->makeLeaveIn($this->hcA1);

        $response = $this->approveViaWebRoute($this->director(), $leave);

        $response->assertRedirect(route('leave.approval'));
        $this->assertSame(1, (int) $leave->fresh()->is_approved, 'Native approver must be able to approve via the real web route.');
        $this->assertSame('approved', $leave->fresh()->workflow_status);
    }

    public function test_user_without_authority_or_delegation_cannot_approve_via_real_web_route(): void
    {
        $this->giveDirectorNativeLeaveApprovalAuthority();
        $leave = $this->makeLeaveIn($this->hcA1);

        $this->approveViaWebRoute($this->deputy(), $leave);

        $this->assertSame(0, (int) $leave->fresh()->is_approved, 'A user with no native authority and no delegation must not be able to approve.');
        $this->assertSame('pending', $leave->fresh()->workflow_status);
    }

    public function test_delegatee_can_approve_from_within_delegated_scope_via_real_web_route(): void
    {
        $this->giveDirectorNativeLeaveApprovalAuthority();
        $this->delegateLeaveToDeputy(); // scoped to OD A / UNIT_TREE
        $leave = $this->makeLeaveIn($this->hcA1); // HC A1 is under OD A

        $response = $this->approveViaWebRoute($this->deputy(), $leave);

        $response->assertRedirect(route('leave.approval'));
        $this->assertSame(1, (int) $leave->fresh()->is_approved, 'Delegatee must be able to approve leave sourced from inside the delegated scope.');
    }

    public function test_delegatee_cannot_approve_outside_delegated_scope_via_real_web_route(): void
    {
        $this->giveDirectorNativeLeaveApprovalAuthority();
        $this->delegateLeaveToDeputy(); // scoped to OD A / UNIT_TREE only
        $leave = $this->makeLeaveIn($this->hcB1); // HC B1 is under OD B, NOT delegated

        $this->approveViaWebRoute($this->deputy(), $leave);

        $this->assertSame(0, (int) $leave->fresh()->is_approved, 'Delegatee must NOT be able to approve leave sourced from outside the delegated scope.');
        $this->assertSame('pending', $leave->fresh()->workflow_status);
    }

    public function test_revoked_delegation_cannot_approve_via_real_web_route(): void
    {
        $this->giveDirectorNativeLeaveApprovalAuthority();
        $delegation = $this->delegateLeaveToDeputy();
        app(DelegationService::class)->revoke($delegation, self::DIRECTOR_ID);
        $leave = $this->makeLeaveIn($this->hcA1);

        $this->approveViaWebRoute($this->deputy(), $leave);

        $this->assertSame(0, (int) $leave->fresh()->is_approved, 'A revoked delegation must not grant approval via the real web route.');
    }

    public function test_expired_delegation_cannot_approve_via_real_web_route(): void
    {
        $this->giveDirectorNativeLeaveApprovalAuthority();
        Delegation::create([
            'delegator_user_id' => self::DIRECTOR_ID,
            'delegatee_user_id' => self::DEPUTY_ID,
            'authority_type' => Delegation::AUTHORITY_TYPE_WORKFLOW_APPROVAL,
            'authority_module_key' => 'leave',
            'scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
            'scope_department_ids' => [$this->odA->id],
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'status' => Delegation::STATUS_ACTIVE,
        ]);
        $leave = $this->makeLeaveIn($this->hcA1);

        $this->approveViaWebRoute($this->deputy(), $leave);

        $this->assertSame(0, (int) $leave->fresh()->is_approved, 'An expired delegation must not grant approval via the real web route.');
    }

    public function test_requester_cannot_approve_own_leave_even_with_full_delegation(): void
    {
        $this->giveDirectorNativeLeaveApprovalAuthority();
        // Organization-wide delegation, wide enough that it *would* cover
        // this department if self-approval prevention were not enforced.
        $this->delegateLeaveToDeputy([
            'scope_type' => UserAssignment::SCOPE_ALL,
            'scope_department_ids' => [],
        ]);
        $leave = $this->makeLeaveIn($this->hcA1, requesterUserId: self::DEPUTY_ID);

        $this->approveViaWebRoute($this->deputy(), $leave);

        $this->assertSame(0, (int) $leave->fresh()->is_approved, 'A requester must never be able to approve their own leave, delegation or not.');
    }

    public function test_delegation_for_a_different_module_does_not_grant_leave_approval_via_real_web_route(): void
    {
        $this->giveDirectorNativeLeaveApprovalAuthority();
        $this->makeUnrelatedModuleDelegatable();
        $this->delegateLeaveToDeputy([
            'authority_module_key' => 'phpunit_leaveweb_other_module',
            'scope_type' => UserAssignment::SCOPE_ALL,
            'scope_department_ids' => [],
        ]);
        $leave = $this->makeLeaveIn($this->hcA1);

        $this->approveViaWebRoute($this->deputy(), $leave);

        $this->assertSame(0, (int) $leave->fresh()->is_approved, 'A delegation scoped to a different module must not grant Leave approval.');
    }
}
