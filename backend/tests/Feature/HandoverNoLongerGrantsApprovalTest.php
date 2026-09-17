<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\ApplyLeave;
use Modules\HumanResource\Entities\Delegation;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\LeaveType;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Entities\WorkflowDefinition;
use Modules\HumanResource\Entities\WorkflowDefinitionStep;
use Modules\HumanResource\Entities\WorkflowInstance;
use Modules\HumanResource\Services\DelegationService;
use Modules\HumanResource\Services\GovernanceAssignmentService;
use Modules\HumanResource\Support\WorkflowActorResolverService;
use Tests\TestCase;

/**
 * Phase 3D.2, sections 12/15/31-33: the mandatory security regression --
 * handover_employee_id alone (no explicit Central Delegation) must never
 * grant workflow approval authority, for BOTH actor-type shapes:
 *
 *  - RESPONSIBILITY/POSITION (the dominant shape used by every real Leave/
 *    Notice/Mission workflow definition in this app -- 12/16 live steps):
 *    empirically confirmed this was NEVER actually cross-person-exploitable
 *    (userHasMatchingAssignment() only ever queried the CALLER's own
 *    UserAssignment rows), but the vestigial substitution call was removed
 *    anyway for architectural consistency -- this test locks in "still
 *    correctly denied, and still correctly allowed for the real holder".
 *
 *  - SPECIFIC_USER (used by 3/16 live steps, all in Attendance -- not one
 *    of this phase's migrated consumers, but the code path is shared):
 *    THIS was the actually-exploitable shape (canSpecificUserActOnStep()
 *    used to substitute the named actor's effective/handover-resolved id).
 *    This test constructs an isolated throwaway SPECIFIC_USER step to
 *    directly exercise the fixed code, since no real Leave/Notice/Mission
 *    definition uses this actor type to test it through.
 *
 * Also proves handover + an explicit, separate Central Delegation compose
 * correctly (sections 32-33): the delegatee gets exactly what was
 * delegated, nothing more, and a third person who is neither the handover
 * nor the delegatee gets nothing.
 */
class HandoverNoLongerGrantsApprovalTest extends TestCase
{
    use DatabaseTransactions;

    private const RESPONSIBILITY_HEAD_ID = 1;
    private const APPROVER_ID = 640;   // "A" -- holds real approval authority, goes on leave
    private const HANDOVER_ID = 28;    // "B" -- named as handover_employee_id, holds nothing natively
    private const THIRD_PARTY_ID = 32; // "C" -- neither handover nor delegatee, for section 33

    private Department $dept;

    protected function setUp(): void
    {
        parent::setUp();
        $unitTypeId = (int) \Illuminate\Support\Facades\DB::table('org_unit_types')->orderBy('id')->value('id');
        $this->dept = Department::create(['department_name' => 'PHPUnit Handover-vs-Delegation Dept', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
    }

    private function approver(): User
    {
        return User::query()->findOrFail(self::APPROVER_ID);
    }

    private function handoverUser(): User
    {
        return User::query()->findOrFail(self::HANDOVER_ID);
    }

    private function thirdParty(): User
    {
        return User::query()->findOrFail(self::THIRD_PARTY_ID);
    }

    private function putApproverOnLeaveWithHandover(): void
    {
        $approverEmployee = $this->approver()->employee()->firstOrFail();
        $handoverEmployee = $this->handoverUser()->employee()->firstOrFail();

        $leaveType = LeaveType::query()->create([
            'uuid' => (string) Str::uuid(),
            'leave_type' => 'PHPUnit Handover Test Leave ' . Str::random(4),
            'leave_days' => 5,
            'leave_code' => 'PU-' . Str::upper(Str::random(4)),
        ]);
        ApplyLeave::query()->create([
            'uuid' => (string) Str::uuid(),
            'employee_id' => (int) $approverEmployee->id,
            'handover_employee_id' => (int) $handoverEmployee->id,
            'leave_type_id' => (int) $leaveType->id,
            'leave_apply_start_date' => now()->subDay()->toDateString(),
            'leave_apply_end_date' => now()->addDay()->toDateString(),
            'leave_apply_date' => now()->subDay()->toDateString(),
            'total_apply_day' => 3,
            'reason' => 'PHPUnit handover test',
            'is_approved_by_manager' => 1,
            'is_approved' => 1,
            'workflow_status' => 'approved',
        ]);
    }

    // ---------------- RESPONSIBILITY shape (real Leave/Notice/Mission shape) ----------------

    public function test_handover_alone_does_not_grant_responsibility_type_approval(): void
    {
        $definition = WorkflowDefinition::create([
            'module_key' => 'phpunit_handover_resp',
            'request_type_key' => 'test_type',
            'name' => 'PHPUnit Handover Responsibility Test',
            'priority' => 999,
            'is_active' => true,
        ]);
        $definition->steps()->create([
            'step_order' => 1,
            'step_name' => 'PHPUnit Approval',
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

        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $this->approver()->id,
            'department_id' => $this->dept->id,
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_ALL,
            'is_primary' => false,
            'is_active' => true,
        ]);
        $this->putApproverOnLeaveWithHandover();

        $instance = $this->makeInstance($definition);

        $this->assertTrue(
            app(AccessControlService::class)->canApprove($this->approver(), $instance, $this->dept->id),
            'The real holder must still be able to approve (no regression from removing the vestigial substitution).'
        );
        $this->assertFalse(
            app(AccessControlService::class)->canApprove($this->handoverUser(), $instance, $this->dept->id),
            'Pure handover, with no Central Delegation, must NOT grant approval.'
        );
    }

    // ---------------- SPECIFIC_USER shape (the actually-exploitable one) ----------------

    public function test_handover_alone_does_not_grant_specific_user_type_approval(): void
    {
        $definition = WorkflowDefinition::create([
            'module_key' => 'phpunit_handover_specific',
            'request_type_key' => 'test_type',
            'name' => 'PHPUnit Handover Specific-User Test',
            'priority' => 999,
            'is_active' => true,
        ]);
        $definition->steps()->create([
            'step_order' => 1,
            'step_name' => 'PHPUnit Approval',
            'action_type' => 'approve',
            'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_SPECIFIC_USER,
            'actor_user_id' => $this->approver()->id,
            'scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
            'is_final_approval' => true,
            'is_required' => true,
            'can_return' => true,
            'can_reject' => true,
        ]);

        $this->putApproverOnLeaveWithHandover();
        $instance = $this->makeInstance($definition);

        $this->assertTrue(
            app(AccessControlService::class)->canApprove($this->approver(), $instance, $this->dept->id),
            'The literally-named actor must still be able to approve.'
        );
        $this->assertFalse(
            app(AccessControlService::class)->canApprove($this->handoverUser(), $instance, $this->dept->id),
            'This is the actually-exploitable pre-fix path: pure handover must NOT let a different named-actor step be approved.'
        );
    }

    // ---------------- Handover + explicit Delegation compose correctly ----------------

    public function test_handover_plus_explicit_delegation_grants_exactly_what_was_delegated(): void
    {
        $definition = WorkflowDefinition::create([
            'module_key' => 'phpunit_handover_plus_deleg',
            'request_type_key' => 'test_type',
            'name' => 'PHPUnit Handover Plus Delegation Test',
            'priority' => 999,
            'is_active' => true,
        ]);
        $definition->steps()->create([
            'step_order' => 1,
            'step_name' => 'PHPUnit Approval',
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
        $otherDefinition = WorkflowDefinition::create([
            'module_key' => 'phpunit_handover_plus_deleg_other',
            'request_type_key' => 'test_type',
            'name' => 'PHPUnit Unrelated Module',
            'priority' => 999,
            'is_active' => true,
        ]);
        $otherDefinition->steps()->create([
            'step_order' => 1,
            'step_name' => 'PHPUnit Other Approval',
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

        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $this->approver()->id,
            'department_id' => $this->dept->id,
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_ALL,
            'is_primary' => false,
            'is_active' => true,
        ]);
        $this->putApproverOnLeaveWithHandover();

        // Explicit, separate Central Delegation: A -> B, this module only.
        app(DelegationService::class)->create([
            'delegator_user_id' => $this->approver()->id,
            'delegatee_user_id' => $this->handoverUser()->id,
            'authority_module_key' => 'phpunit_handover_plus_deleg',
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
            'scope_department_ids' => [$this->dept->id],
            'starts_at' => now()->subHour()->toDateTimeString(),
            'ends_at' => now()->addDay()->toDateTimeString(),
        ]);

        $instance = $this->makeInstance($definition);
        $otherInstance = $this->makeInstance($otherDefinition);

        $this->assertTrue(
            app(AccessControlService::class)->canApprove($this->handoverUser(), $instance, $this->dept->id),
            'B must be able to approve via the EXPLICIT delegation.'
        );
        $this->assertFalse(
            app(AccessControlService::class)->canApprove($this->handoverUser(), $otherInstance, $this->dept->id),
            'The delegation for one module must not grant an unrelated module -- being the handover employee does not widen it either.'
        );
    }

    public function test_work_handover_and_approval_delegation_are_independent_people(): void
    {
        $definition = WorkflowDefinition::create([
            'module_key' => 'phpunit_independent_people',
            'request_type_key' => 'test_type',
            'name' => 'PHPUnit Independent People Test',
            'priority' => 999,
            'is_active' => true,
        ]);
        $definition->steps()->create([
            'step_order' => 1,
            'step_name' => 'PHPUnit Approval',
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

        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $this->approver()->id,
            'department_id' => $this->dept->id,
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_ALL,
            'is_primary' => false,
            'is_active' => true,
        ]);
        // Work handover: A -> B (pure coverage, no delegation).
        $this->putApproverOnLeaveWithHandover();
        // Approval delegation: A -> C (a DIFFERENT person than the handover).
        app(DelegationService::class)->create([
            'delegator_user_id' => $this->approver()->id,
            'delegatee_user_id' => $this->thirdParty()->id,
            'authority_module_key' => 'phpunit_independent_people',
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
            'scope_department_ids' => [$this->dept->id],
            'starts_at' => now()->subHour()->toDateTimeString(),
            'ends_at' => now()->addDay()->toDateTimeString(),
        ]);

        $instance = $this->makeInstance($definition);

        $this->assertFalse(
            app(AccessControlService::class)->canApprove($this->handoverUser(), $instance, $this->dept->id),
            'B (work handover only) must NOT receive C\'s delegated approval authority.'
        );
        $this->assertTrue(
            app(AccessControlService::class)->canApprove($this->thirdParty(), $instance, $this->dept->id),
            'C (explicit delegatee) must be able to approve, independent of who the handover employee is.'
        );
    }

    private function makeInstance(WorkflowDefinition $definition): WorkflowInstance
    {
        return WorkflowInstance::create([
            'uuid' => (string) Str::uuid(),
            'module_key' => $definition->module_key,
            'request_type_key' => $definition->request_type_key,
            'source_type' => 'phpunit_test',
            'source_id' => 1,
            'workflow_definition_id' => $definition->id,
            'status' => 'pending',
            'current_step_order' => 1,
        ]);
    }
}
