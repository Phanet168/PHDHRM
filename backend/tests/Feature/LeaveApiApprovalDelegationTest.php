<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
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
 * Phase 3D.2, section 6/7: proves the REAL Leave API review path
 * (POST api.v1.leave_requests.review -> LeaveRequestApiController::review
 * -> canUserActOnWorkflowStep) is delegation-aware after being migrated
 * onto AccessControlService::canApprove(), the same seam LeaveController
 * (web) uses -- Web and API must converge on one decision. Mirrors
 * LeaveWebApprovalDelegationTest's fixture shape exactly. Runs against the
 * real (dev) database, wrapped in a transaction that is rolled back
 * afterwards.
 */
class LeaveApiApprovalDelegationTest extends TestCase
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
        $this->phd = Department::create(['department_name' => 'PHPUnit LeaveApi PHD', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
        $this->odA = Department::create(['department_name' => 'PHPUnit LeaveApi OD A', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->phd->id, 'is_active' => true]);
        $this->hcA1 = Department::create(['department_name' => 'PHPUnit LeaveApi HC A1', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->odA->id, 'is_active' => true]);
        $this->odB = Department::create(['department_name' => 'PHPUnit LeaveApi OD B', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->phd->id, 'is_active' => true]);
        $this->hcB1 = Department::create(['department_name' => 'PHPUnit LeaveApi HC B1', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->odB->id, 'is_active' => true]);
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

    private function makeLeaveIn(Department $department, ?int $requesterUserId = null): ApplyLeave
    {
        $employee = new Employee([
            'department_id' => $department->id,
            'user_id' => $requesterUserId,
            'first_name' => 'PHPUnit',
            'last_name' => 'LeaveApiSubject',
            'is_active' => 1,
        ]);
        $employee->employee_id = 'PHPUNIT-' . Str::upper(Str::random(8));
        $employee->save();

        $leaveType = LeaveType::query()->create([
            'uuid' => (string) Str::uuid(),
            'leave_type' => 'PHPUnit LeaveApi Type ' . Str::random(6),
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
            'reason' => 'PHPUnit leave api delegation test',
            'is_approved_by_manager' => 0,
            'is_approved' => 0,
            'workflow_status' => 'pending',
        ]);

        $definition = WorkflowDefinition::create([
            'module_key' => 'leave',
            'request_type_key' => 'leave_request',
            'name' => 'PHPUnit LeaveApi Definition',
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

    private function reviewViaApiRoute(User $actor, ApplyLeave $leave, string $action = 'approve'): \Illuminate\Testing\TestResponse
    {
        Sanctum::actingAs($actor);

        return $this->postJson(route('api.v1.leave_requests.review', ['leaveRequest' => $leave->id]), [
            'action' => $action,
            'note' => 'PHPUnit API decision',
        ]);
    }

    public function test_native_approver_can_approve_via_real_api_route(): void
    {
        $this->giveDirectorNativeLeaveApprovalAuthority();
        $leave = $this->makeLeaveIn($this->hcA1);

        $response = $this->reviewViaApiRoute($this->director(), $leave);

        $response->assertOk()->assertJsonPath('response.status', 'ok');
        $this->assertSame(1, (int) $leave->fresh()->is_approved, 'Native approver must be able to approve via the real API route.');
    }

    public function test_user_without_authority_or_delegation_cannot_approve_via_real_api_route(): void
    {
        $this->giveDirectorNativeLeaveApprovalAuthority();
        $leave = $this->makeLeaveIn($this->hcA1);

        $response = $this->reviewViaApiRoute($this->deputy(), $leave);

        $response->assertStatus(403);
        $this->assertSame(0, (int) $leave->fresh()->is_approved);
    }

    public function test_delegatee_can_approve_from_within_delegated_scope_via_real_api_route(): void
    {
        $this->giveDirectorNativeLeaveApprovalAuthority();
        $this->delegateLeaveToDeputy(); // scoped to OD A / UNIT_TREE
        $leave = $this->makeLeaveIn($this->hcA1); // HC A1 is under OD A

        $response = $this->reviewViaApiRoute($this->deputy(), $leave);

        $response->assertOk()->assertJsonPath('response.status', 'ok');
        $this->assertSame(1, (int) $leave->fresh()->is_approved, 'Delegatee must be able to approve leave sourced from inside the delegated scope.');
    }

    public function test_delegatee_cannot_approve_outside_delegated_scope_via_real_api_route(): void
    {
        $this->giveDirectorNativeLeaveApprovalAuthority();
        $this->delegateLeaveToDeputy(); // scoped to OD A / UNIT_TREE only
        $leave = $this->makeLeaveIn($this->hcB1); // HC B1 is under OD B, NOT delegated

        $response = $this->reviewViaApiRoute($this->deputy(), $leave);

        $response->assertStatus(403);
        $this->assertSame(0, (int) $leave->fresh()->is_approved, 'Delegatee must NOT be able to approve leave sourced from outside the delegated scope.');
    }

    public function test_revoked_delegation_cannot_approve_via_real_api_route(): void
    {
        $this->giveDirectorNativeLeaveApprovalAuthority();
        $delegation = $this->delegateLeaveToDeputy();
        app(DelegationService::class)->revoke($delegation, self::DIRECTOR_ID);
        $leave = $this->makeLeaveIn($this->hcA1);

        $response = $this->reviewViaApiRoute($this->deputy(), $leave);

        $response->assertStatus(403);
        $this->assertSame(0, (int) $leave->fresh()->is_approved);
    }

    public function test_requester_cannot_approve_own_leave_even_with_full_delegation(): void
    {
        $this->giveDirectorNativeLeaveApprovalAuthority();
        $this->delegateLeaveToDeputy([
            'scope_type' => UserAssignment::SCOPE_ALL,
            'scope_department_ids' => [],
        ]);
        $leave = $this->makeLeaveIn($this->hcA1, requesterUserId: self::DEPUTY_ID);

        $response = $this->reviewViaApiRoute($this->deputy(), $leave);

        $response->assertStatus(403);
        $this->assertSame(0, (int) $leave->fresh()->is_approved);
    }

    public function test_web_and_api_converge_on_the_same_decision(): void
    {
        $this->giveDirectorNativeLeaveApprovalAuthority();
        $this->delegateLeaveToDeputy();
        $leaveForApi = $this->makeLeaveIn($this->hcA1);
        $leaveForWeb = $this->makeLeaveIn($this->hcA1);

        $apiResponse = $this->reviewViaApiRoute($this->deputy(), $leaveForApi);
        $apiResponse->assertOk();

        $webResponse = $this->actingAs($this->deputy())->put(route('leave.approved-by-manager', $leaveForWeb->uuid), [
            'leave_approved_start_date' => $leaveForWeb->leave_apply_start_date,
            'leave_approved_end_date' => $leaveForWeb->leave_apply_end_date,
            'total_approved_day' => 1,
            'description' => 'PHPUnit convergence check',
        ]);
        $webResponse->assertRedirect(route('leave.approval'));

        $this->assertSame(1, (int) $leaveForApi->fresh()->is_approved);
        $this->assertSame(1, (int) $leaveForWeb->fresh()->is_approved, 'Web and API must converge on the same authorization decision for an identically-scoped delegation.');
    }
}
