<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\Delegation;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Mission;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Entities\WorkflowDefinition;
use Modules\HumanResource\Entities\WorkflowDefinitionStep;
use Modules\HumanResource\Entities\WorkflowInstance;
use Modules\HumanResource\Enums\Mission\MissionCreationPath;
use Modules\HumanResource\Enums\Mission\MissionCreationSource;
use Modules\HumanResource\Enums\Mission\MissionStatus;
use Modules\HumanResource\Services\DelegationService;
use Modules\HumanResource\Services\GovernanceAssignmentService;
use Tests\TestCase;

/**
 * Phase 3D.2, section 10/11: proves the REAL Mission web decide path
 * (POST missions.decide -> MissionController::decide ->
 * MissionWorkflowService::canAct()/decide()) is delegation-aware after
 * being migrated onto AccessControlService::canApprove(), the same seam
 * Leave/Notice now use. Does not touch assignments, financial rules,
 * lifecycle, or notifications. Runs against the real (dev) database,
 * wrapped in a transaction that is rolled back afterwards.
 */
class MissionApprovalDelegationTest extends TestCase
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
        $this->phd = Department::create(['department_name' => 'PHPUnit Mission PHD', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
        $this->odA = Department::create(['department_name' => 'PHPUnit Mission OD A', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->phd->id, 'is_active' => true]);
        $this->hcA1 = Department::create(['department_name' => 'PHPUnit Mission HC A1', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->odA->id, 'is_active' => true]);
        $this->odB = Department::create(['department_name' => 'PHPUnit Mission OD B', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->phd->id, 'is_active' => true]);
        $this->hcB1 = Department::create(['department_name' => 'PHPUnit Mission HC B1', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->odB->id, 'is_active' => true]);
    }

    private function director(): User
    {
        return User::query()->findOrFail(self::DIRECTOR_ID);
    }

    private function deputy(): User
    {
        return User::query()->findOrFail(self::DEPUTY_ID);
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
            'request_type_key' => $moduleKey === 'mission' ? 'mission_request' : 'test_type',
            'name' => 'PHPUnit Mission-adjacent Definition ' . $moduleKey,
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
            'authority_module_key' => 'mission',
            'scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
            'scope_department_ids' => [$this->odA->id],
            'starts_at' => now()->subHour()->toDateTimeString(),
            'ends_at' => now()->addDay()->toDateTimeString(),
        ], $overrides));
    }

    private ?WorkflowDefinition $cachedMissionDefinition = null;

    private function missionDefinition(): WorkflowDefinition
    {
        if ($this->cachedMissionDefinition) {
            return $this->cachedMissionDefinition;
        }

        $definition = WorkflowDefinition::create([
            'module_key' => 'mission',
            'request_type_key' => 'mission_request',
            'name' => 'PHPUnit Mission Definition',
            'priority' => 999,
            'is_active' => true,
        ]);
        $definition->steps()->create([
            'step_order' => 1,
            'step_name' => 'PHPUnit Mission Approval',
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

        return $this->cachedMissionDefinition = $definition;
    }

    private function makeMissionSourcedIn(Department $department, ?int $requesterUserId = null): Mission
    {
        $employee = new Employee([
            'department_id' => $department->id,
            'user_id' => $requesterUserId,
            'first_name' => 'PHPUnit',
            'last_name' => 'MissionRequester',
            'is_active' => 1,
        ]);
        $employee->employee_id = 'PHPUNIT-' . Str::upper(Str::random(8));
        $employee->save();

        $mission = Mission::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'PHPUnit Mission Delegation Test',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'destination' => 'PHPUnit Destination',
            'purpose' => 'PHPUnit purpose',
            'status' => 'pending',
            'requester_employee_id' => (int) $employee->id,
            'source_department_id' => $department->id,
            'creation_path' => MissionCreationPath::EmployeeRequest,
            'creation_source' => MissionCreationSource::EmployeeRequest,
        ]);

        $definition = $this->missionDefinition();
        $instance = WorkflowInstance::create([
            'uuid' => (string) Str::uuid(),
            'module_key' => 'mission',
            'request_type_key' => 'mission_request',
            'source_type' => Mission::class,
            'source_id' => (int) $mission->id,
            'workflow_definition_id' => (int) $definition->id,
            'status' => 'pending',
            'current_step_order' => 1,
            'submitted_by' => (int) ($requesterUserId ?: self::DIRECTOR_ID),
            'submitted_at' => now(),
        ]);

        $mission->forceFill([
            'workflow_instance_id' => (int) $instance->id,
            'workflow_status' => 'pending',
            'workflow_current_step_order' => 1,
            'lifecycle_status' => MissionStatus::PendingDirector,
        ])->save();

        return $mission->fresh();
    }

    private function decideViaWebRoute(User $actor, Mission $mission, string $decision = 'approve'): \Illuminate\Testing\TestResponse
    {
        $payload = ['decision' => $decision];
        if (in_array($decision, ['reject', 'return'], true)) {
            $payload['note'] = 'PHPUnit decision note';
        }

        return $this->actingAs($actor)->post(route('missions.decide', $mission->id), $payload);
    }

    public function test_native_approver_can_decide_via_real_web_route(): void
    {
        $this->giveDirectorNativeApprovalAuthority();
        $mission = $this->makeMissionSourcedIn($this->hcA1);

        $response = $this->decideViaWebRoute($this->director(), $mission);

        $response->assertRedirect(route('missions.show', $mission->id));
        $this->assertSame('approved', (string) $mission->fresh()->workflow_status);
    }

    public function test_user_without_authority_or_delegation_cannot_decide_via_real_web_route(): void
    {
        $this->giveDirectorNativeApprovalAuthority();
        $mission = $this->makeMissionSourcedIn($this->hcA1);

        $response = $this->decideViaWebRoute($this->deputy(), $mission);

        $response->assertStatus(403);
        $this->assertSame('pending', (string) $mission->fresh()->workflow_status);
    }

    public function test_delegatee_can_decide_from_within_delegated_scope_via_real_web_route(): void
    {
        $this->giveDirectorNativeApprovalAuthority();
        $this->delegate();
        $mission = $this->makeMissionSourcedIn($this->hcA1);

        $response = $this->decideViaWebRoute($this->deputy(), $mission);

        $response->assertRedirect(route('missions.show', $mission->id));
        $this->assertSame('approved', (string) $mission->fresh()->workflow_status);
    }

    public function test_delegatee_cannot_decide_outside_delegated_scope_via_real_web_route(): void
    {
        $this->giveDirectorNativeApprovalAuthority();
        $this->delegate(); // scoped to OD A only
        $mission = $this->makeMissionSourcedIn($this->hcB1);

        $response = $this->decideViaWebRoute($this->deputy(), $mission);

        $response->assertStatus(403);
        $this->assertSame('pending', (string) $mission->fresh()->workflow_status);
    }

    public function test_revoked_delegation_cannot_decide_via_real_web_route(): void
    {
        $this->giveDirectorNativeApprovalAuthority();
        $delegation = $this->delegate();
        app(DelegationService::class)->revoke($delegation, self::DIRECTOR_ID);
        $mission = $this->makeMissionSourcedIn($this->hcA1);

        $response = $this->decideViaWebRoute($this->deputy(), $mission);

        $response->assertStatus(403);
    }

    public function test_expired_delegation_cannot_decide_via_real_web_route(): void
    {
        $this->giveDirectorNativeApprovalAuthority();
        Delegation::create([
            'delegator_user_id' => self::DIRECTOR_ID,
            'delegatee_user_id' => self::DEPUTY_ID,
            'authority_type' => Delegation::AUTHORITY_TYPE_WORKFLOW_APPROVAL,
            'authority_module_key' => 'mission',
            'scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
            'scope_department_ids' => [$this->odA->id],
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'status' => Delegation::STATUS_ACTIVE,
        ]);
        $mission = $this->makeMissionSourcedIn($this->hcA1);

        $response = $this->decideViaWebRoute($this->deputy(), $mission);

        $response->assertStatus(403);
    }

    public function test_requester_cannot_decide_own_mission_even_with_full_delegation(): void
    {
        $this->giveDirectorNativeApprovalAuthority();
        $this->delegate([
            'scope_type' => UserAssignment::SCOPE_ALL,
            'scope_department_ids' => [],
        ]);
        $mission = $this->makeMissionSourcedIn($this->hcA1, requesterUserId: self::DEPUTY_ID);

        $response = $this->decideViaWebRoute($this->deputy(), $mission);

        $response->assertStatus(403);
    }

    public function test_leave_delegation_does_not_grant_mission_approval(): void
    {
        $this->giveDirectorNativeApprovalAuthority();
        $this->makeDelegatableDefinition('leave');
        $this->delegate([
            'authority_module_key' => 'leave',
            'scope_type' => UserAssignment::SCOPE_ALL,
            'scope_department_ids' => [],
        ]);
        $mission = $this->makeMissionSourcedIn($this->hcA1);

        $response = $this->decideViaWebRoute($this->deputy(), $mission);

        $response->assertStatus(403); // A Leave-scoped delegation must not grant Mission approval.
    }

    public function test_notice_delegation_does_not_grant_mission_approval(): void
    {
        $this->giveDirectorNativeApprovalAuthority();
        $this->makeDelegatableDefinition('notice');
        $this->delegate([
            'authority_module_key' => 'notice',
            'scope_type' => UserAssignment::SCOPE_ALL,
            'scope_department_ids' => [],
        ]);
        $mission = $this->makeMissionSourcedIn($this->hcA1);

        $response = $this->decideViaWebRoute($this->deputy(), $mission);

        $response->assertStatus(403); // A Notice-scoped delegation must not grant Mission approval.
    }

    public function test_reject_decision_via_real_web_route_is_also_delegation_aware(): void
    {
        $this->giveDirectorNativeApprovalAuthority();
        $this->delegate();
        $mission = $this->makeMissionSourcedIn($this->hcA1);

        $response = $this->decideViaWebRoute($this->deputy(), $mission, 'reject');

        $response->assertRedirect(route('missions.show', $mission->id));
        $this->assertSame('rejected', (string) $mission->fresh()->workflow_status);
    }
}
