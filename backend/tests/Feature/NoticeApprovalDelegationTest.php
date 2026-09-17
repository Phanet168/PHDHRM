<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\Delegation;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Notice;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Entities\WorkflowDefinition;
use Modules\HumanResource\Entities\WorkflowDefinitionStep;
use Modules\HumanResource\Entities\WorkflowInstance;
use Modules\HumanResource\Services\DelegationService;
use Modules\HumanResource\Services\GovernanceAssignmentService;
use Tests\TestCase;

/**
 * Phase 3D.2, section 8/9: proves the REAL Notice web approval path
 * (POST notice.approve -> NoticeController::approve ->
 * applyNoticeWorkflowDecision -> canUserActOnNoticeStep) is
 * delegation-aware after being migrated onto
 * AccessControlService::canApprove(). This intentionally does NOT touch
 * Notice visibility/listing -- "can SEE Notice" is a separate, unmigrated
 * question. Notice has no requester self-approval restriction today (it
 * never had one), so none is asserted here -- adding one would be inventing
 * new business behavior, which this phase forbids. Runs against the real
 * (dev) database, wrapped in a transaction that is rolled back afterwards.
 */
class NoticeApprovalDelegationTest extends TestCase
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
        $this->phd = Department::create(['department_name' => 'PHPUnit Notice PHD', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
        $this->odA = Department::create(['department_name' => 'PHPUnit Notice OD A', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->phd->id, 'is_active' => true]);
        $this->hcA1 = Department::create(['department_name' => 'PHPUnit Notice HC A1', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->odA->id, 'is_active' => true]);
        $this->odB = Department::create(['department_name' => 'PHPUnit Notice OD B', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->phd->id, 'is_active' => true]);
        $this->hcB1 = Department::create(['department_name' => 'PHPUnit Notice HC B1', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->odB->id, 'is_active' => true]);
    }

    private function director(): User
    {
        $user = User::query()->findOrFail(self::DIRECTOR_ID);
        $user->givePermissionTo('update_notice');
        $user->givePermissionTo('create_notice');

        return $user;
    }

    private function deputy(): User
    {
        $user = User::query()->findOrFail(self::DEPUTY_ID);
        $user->givePermissionTo('update_notice');

        return $user;
    }

    private function giveDirectorNativeApprovalAuthority(): void
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

    private function makeDelegatableDefinition(string $moduleKey): void
    {
        $definition = WorkflowDefinition::create([
            'module_key' => $moduleKey,
            'request_type_key' => $moduleKey === 'notice' ? 'notice_general' : 'test_type',
            'name' => 'PHPUnit Notice-adjacent Definition ' . $moduleKey,
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
            'scope_type' => UserAssignment::SCOPE_ALL,
            'is_final_approval' => true,
            'is_required' => true,
            'can_return' => true,
            'can_reject' => true,
        ]);
    }

    private function delegate(array $overrides = []): Delegation
    {
        return app(DelegationService::class)->create(array_merge([
            'delegator_user_id' => self::DIRECTOR_ID,
            'delegatee_user_id' => self::DEPUTY_ID,
            'authority_module_key' => 'notice',
            'scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
            'scope_department_ids' => [$this->odA->id],
            'starts_at' => now()->subHour()->toDateTimeString(),
            'ends_at' => now()->addDay()->toDateTimeString(),
        ], $overrides));
    }

    private function makeNoticeSourcedIn(Department $department): Notice
    {
        $notice = Notice::create([
            'uuid' => (string) Str::uuid(),
            'notice_descriptiion' => 'PHPUnit notice delegation test',
            'notice_date' => now()->toDateString(),
            'notice_type' => 'general',
            'notice_by' => 'PHPUnit',
            'notice_attachment' => '',
            'status' => Notice::STATUS_PENDING_APPROVAL,
            'audience_type' => Notice::AUDIENCE_ALL,
            'workflow_status' => 'pending',
        ]);

        $definition = $this->noticeDefinition();
        $instance = WorkflowInstance::create([
            'uuid' => (string) Str::uuid(),
            'module_key' => 'notice',
            'request_type_key' => 'notice_general',
            'source_type' => Notice::class,
            'source_id' => (int) $notice->id,
            'workflow_definition_id' => (int) $definition->id,
            'status' => 'pending',
            'current_step_order' => 1,
            'submitted_by' => self::DIRECTOR_ID,
            'submitted_at' => now(),
            'context_json' => ['department_id' => $department->id],
        ]);

        $notice->update([
            'workflow_instance_id' => (int) $instance->id,
            'workflow_status' => 'pending',
            'workflow_current_step_order' => 1,
        ]);

        return $notice->fresh();
    }

    private ?WorkflowDefinition $cachedNoticeDefinition = null;

    private function noticeDefinition(): WorkflowDefinition
    {
        if ($this->cachedNoticeDefinition) {
            return $this->cachedNoticeDefinition;
        }

        $definition = WorkflowDefinition::create([
            'module_key' => 'notice',
            'request_type_key' => 'notice_general',
            'name' => 'PHPUnit Notice Definition',
            'priority' => 999,
            'is_active' => true,
        ]);
        $definition->steps()->create([
            'step_order' => 1,
            'step_name' => 'PHPUnit Notice Approval',
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

        return $this->cachedNoticeDefinition = $definition;
    }

    public function test_native_approver_can_approve_via_real_web_route(): void
    {
        $this->giveDirectorNativeApprovalAuthority();
        $notice = $this->makeNoticeSourcedIn($this->hcA1);

        $response = $this->actingAs($this->director())->post(route('notice.approve', $notice->uuid));

        $response->assertRedirect(route('notice.index'));
        // workflow_status (set by applyNoticeWorkflowDecision, the
        // authorization-gated write) is asserted rather than the
        // display-facing status column, which dispatchNotice() may advance
        // further to 'sent' immediately afterward -- dispatch behavior is
        // unrelated business logic this phase does not touch.
        $this->assertSame('approved', (string) $notice->fresh()->workflow_status);
    }

    public function test_user_without_authority_or_delegation_cannot_approve_via_real_web_route(): void
    {
        $this->giveDirectorNativeApprovalAuthority();
        $notice = $this->makeNoticeSourcedIn($this->hcA1);

        $this->actingAs($this->deputy())->post(route('notice.approve', $notice->uuid));

        $this->assertSame(Notice::STATUS_PENDING_APPROVAL, (string) $notice->fresh()->status);
    }

    public function test_delegatee_can_approve_from_within_delegated_scope_via_real_web_route(): void
    {
        $this->giveDirectorNativeApprovalAuthority();
        $this->delegate();
        $notice = $this->makeNoticeSourcedIn($this->hcA1);

        $response = $this->actingAs($this->deputy())->post(route('notice.approve', $notice->uuid));

        $response->assertRedirect(route('notice.index'));
        $this->assertSame('approved', (string) $notice->fresh()->workflow_status, 'Delegatee must be able to approve notice sourced from inside the delegated scope.');
    }

    public function test_delegatee_cannot_approve_outside_delegated_scope_via_real_web_route(): void
    {
        $this->giveDirectorNativeApprovalAuthority();
        $this->delegate(); // scoped to OD A only
        $notice = $this->makeNoticeSourcedIn($this->hcB1);

        $this->actingAs($this->deputy())->post(route('notice.approve', $notice->uuid));

        $this->assertSame(Notice::STATUS_PENDING_APPROVAL, (string) $notice->fresh()->status);
    }

    public function test_revoked_delegation_cannot_approve_via_real_web_route(): void
    {
        $this->giveDirectorNativeApprovalAuthority();
        $delegation = $this->delegate();
        app(DelegationService::class)->revoke($delegation, self::DIRECTOR_ID);
        $notice = $this->makeNoticeSourcedIn($this->hcA1);

        $this->actingAs($this->deputy())->post(route('notice.approve', $notice->uuid));

        $this->assertSame(Notice::STATUS_PENDING_APPROVAL, (string) $notice->fresh()->status);
    }

    public function test_expired_delegation_cannot_approve_via_real_web_route(): void
    {
        $this->giveDirectorNativeApprovalAuthority();
        Delegation::create([
            'delegator_user_id' => self::DIRECTOR_ID,
            'delegatee_user_id' => self::DEPUTY_ID,
            'authority_type' => Delegation::AUTHORITY_TYPE_WORKFLOW_APPROVAL,
            'authority_module_key' => 'notice',
            'scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
            'scope_department_ids' => [$this->odA->id],
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'status' => Delegation::STATUS_ACTIVE,
        ]);
        $notice = $this->makeNoticeSourcedIn($this->hcA1);

        $this->actingAs($this->deputy())->post(route('notice.approve', $notice->uuid));

        $this->assertSame(Notice::STATUS_PENDING_APPROVAL, (string) $notice->fresh()->status);
    }

    public function test_leave_delegation_does_not_grant_notice_approval(): void
    {
        $this->giveDirectorNativeApprovalAuthority();
        $this->makeDelegatableDefinition('leave');
        $this->delegate([
            'authority_module_key' => 'leave',
            'scope_type' => UserAssignment::SCOPE_ALL,
            'scope_department_ids' => [],
        ]);
        $notice = $this->makeNoticeSourcedIn($this->hcA1);

        $this->actingAs($this->deputy())->post(route('notice.approve', $notice->uuid));

        $this->assertSame(Notice::STATUS_PENDING_APPROVAL, (string) $notice->fresh()->status, 'A Leave-scoped delegation must not grant Notice approval.');
    }

    public function test_mission_delegation_does_not_grant_notice_approval(): void
    {
        $this->giveDirectorNativeApprovalAuthority();
        $this->makeDelegatableDefinition('mission');
        $this->delegate([
            'authority_module_key' => 'mission',
            'scope_type' => UserAssignment::SCOPE_ALL,
            'scope_department_ids' => [],
        ]);
        $notice = $this->makeNoticeSourcedIn($this->hcA1);

        $this->actingAs($this->deputy())->post(route('notice.approve', $notice->uuid));

        $this->assertSame(Notice::STATUS_PENDING_APPROVAL, (string) $notice->fresh()->status, 'A Mission-scoped delegation must not grant Notice approval.');
    }

    public function test_reject_via_real_web_route_is_also_delegation_aware(): void
    {
        $this->giveDirectorNativeApprovalAuthority();
        $this->delegate();
        $notice = $this->makeNoticeSourcedIn($this->hcA1);

        $response = $this->actingAs($this->deputy())->post(route('notice.reject', $notice->uuid), [
            'rejected_reason' => 'PHPUnit rejection reason',
        ]);

        $response->assertRedirect(route('notice.index'));
        $this->assertSame(Notice::STATUS_REJECTED, (string) $notice->fresh()->status);
    }
}
