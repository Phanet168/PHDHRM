<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\Delegation;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Entities\WorkflowDefinition;
use Modules\HumanResource\Entities\WorkflowDefinitionStep;
use Modules\HumanResource\Entities\WorkflowInstance;
use Modules\HumanResource\Services\DelegationService;
use Modules\HumanResource\Services\GovernanceAssignmentService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 3D.1, sections 35-38: real workflow behavior through
 * AccessControlService::canApprove() with an active delegation, permanent
 * authorization data left untouched, multi-unit scope, and no cross-module
 * authority leakage. Two isolated throwaway workflow definitions stand in
 * for "Leave Approval" and "Mission Approval" so the test never touches
 * real production Leave/Mission data. Runs against the real (dev) database,
 * wrapped in a transaction that is rolled back afterwards.
 */
class DelegationApprovalIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    private const RESPONSIBILITY_HEAD_ID = 1;
    private const DIRECTOR_ID = 640;
    private const DEPUTY_ID = 26;

    private Department $phd;
    private Department $odA;
    private Department $hcA1;
    private Department $hcA2;
    private Department $odB;
    private Department $hcB1;

    private WorkflowDefinition $leaveLikeDefinition;
    private WorkflowDefinition $missionLikeDefinition;

    protected function setUp(): void
    {
        parent::setUp();

        $unitTypeId = (int) \Illuminate\Support\Facades\DB::table('org_unit_types')->orderBy('id')->value('id');
        $this->phd = Department::create(['department_name' => 'PHPUnit Approval PHD', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
        $this->odA = Department::create(['department_name' => 'PHPUnit Approval OD A', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->phd->id, 'is_active' => true]);
        $this->hcA1 = Department::create(['department_name' => 'PHPUnit Approval HC A1', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->odA->id, 'is_active' => true]);
        $this->hcA2 = Department::create(['department_name' => 'PHPUnit Approval HC A2', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->odA->id, 'is_active' => true]);
        $this->odB = Department::create(['department_name' => 'PHPUnit Approval OD B', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->phd->id, 'is_active' => true]);
        $this->hcB1 = Department::create(['department_name' => 'PHPUnit Approval HC B1', 'unit_type_id' => $unitTypeId, 'parent_id' => $this->odB->id, 'is_active' => true]);

        $this->leaveLikeDefinition = $this->makeDefinition('phpunit_leave_like');
        $this->missionLikeDefinition = $this->makeDefinition('phpunit_mission_like');
    }

    private function makeDefinition(string $moduleKey): WorkflowDefinition
    {
        $definition = WorkflowDefinition::create([
            'module_key' => $moduleKey,
            'request_type_key' => 'test_type',
            'name' => 'PHPUnit ' . $moduleKey,
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

        return $definition;
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

    private function director(): User
    {
        return User::query()->findOrFail(self::DIRECTOR_ID);
    }

    private function deputy(): User
    {
        return User::query()->findOrFail(self::DEPUTY_ID);
    }

    private function giveDirectorOrganizationWideAuthority(): void
    {
        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $this->director()->id,
            'department_id' => $this->phd->id,
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_ALL,
            'is_primary' => false,
            'is_active' => true,
        ]);
    }

    private function delegate(array $overrides = []): Delegation
    {
        return app(DelegationService::class)->create(array_merge([
            'delegator_user_id' => $this->director()->id,
            'delegatee_user_id' => $this->deputy()->id,
            'authority_module_key' => 'phpunit_leave_like',
            'scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
            'scope_department_ids' => [$this->odA->id],
            'starts_at' => now()->subHour()->toDateTimeString(),
            'ends_at' => now()->addDay()->toDateTimeString(),
        ], $overrides));
    }

    // ---------------- Section 35: real workflow behavior ----------------

    public function test_director_can_approve_natively(): void
    {
        $this->giveDirectorOrganizationWideAuthority();
        $instance = $this->makeInstance($this->leaveLikeDefinition);

        $this->assertTrue(app(AccessControlService::class)->canApprove($this->director(), $instance, $this->odA->id));
    }

    public function test_deputy_cannot_originally_approve(): void
    {
        $this->giveDirectorOrganizationWideAuthority();
        $instance = $this->makeInstance($this->leaveLikeDefinition);

        $this->assertFalse(app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->odA->id));
    }

    public function test_deputy_can_approve_via_active_delegation_within_scope(): void
    {
        $this->giveDirectorOrganizationWideAuthority();
        $this->delegate();
        $instance = $this->makeInstance($this->leaveLikeDefinition);

        $this->assertTrue(app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->odA->id));
        // UNIT_TREE (self_and_children) must include the child unit.
        $this->assertTrue(app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->hcA1->id));
    }

    public function test_deputy_cannot_approve_outside_delegated_scope(): void
    {
        $this->giveDirectorOrganizationWideAuthority();
        $this->delegate(); // scoped to OD A branch only
        $instance = $this->makeInstance($this->leaveLikeDefinition);

        $this->assertFalse(app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->odB->id));
    }

    public function test_deputy_cannot_approve_a_different_module_unless_separately_delegated(): void
    {
        $this->giveDirectorOrganizationWideAuthority();
        $this->delegate(); // only phpunit_leave_like was delegated
        $missionInstance = $this->makeInstance($this->missionLikeDefinition);

        $this->assertFalse(app(AccessControlService::class)->canApprove($this->deputy(), $missionInstance, $this->odA->id));

        // Now separately delegate the mission-like module too.
        $this->delegate(['authority_module_key' => 'phpunit_mission_like']);
        $this->assertTrue(app(AccessControlService::class)->canApprove($this->deputy(), $missionInstance, $this->odA->id));
    }

    public function test_revocation_immediately_removes_delegated_approval(): void
    {
        $this->giveDirectorOrganizationWideAuthority();
        $delegation = $this->delegate();
        $instance = $this->makeInstance($this->leaveLikeDefinition);

        $this->assertTrue(app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->odA->id));

        app(DelegationService::class)->revoke($delegation, $this->director()->id);

        $this->assertFalse(app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->odA->id));
    }

    public function test_expiration_removes_delegated_approval(): void
    {
        $this->giveDirectorOrganizationWideAuthority();
        // Bypass create()'s period validation to plant an already-expired row directly.
        Delegation::create([
            'delegator_user_id' => $this->director()->id,
            'delegatee_user_id' => $this->deputy()->id,
            'authority_type' => Delegation::AUTHORITY_TYPE_WORKFLOW_APPROVAL,
            'authority_module_key' => 'phpunit_leave_like',
            'scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
            'scope_department_ids' => [$this->odA->id],
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'status' => Delegation::STATUS_ACTIVE,
        ]);
        $instance = $this->makeInstance($this->leaveLikeDefinition);

        $this->assertFalse(app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->odA->id));
    }

    public function test_delegator_losing_authority_immediately_stops_the_delegation(): void
    {
        $this->giveDirectorOrganizationWideAuthority();
        $delegation = $this->delegate();
        $instance = $this->makeInstance($this->leaveLikeDefinition);
        $this->assertTrue(app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->odA->id));

        // Director loses their native authority entirely -- revoked the same
        // way a real admin would (through GovernanceAssignmentService, which
        // keeps the legacy user_org_roles mirror row in sync too; a raw
        // UserAssignment::update() bypassing that service would leave a
        // stale active mirror row behind and incorrectly keep resolving via
        // OrgHierarchyAccessService::effectiveOrgRoles()'s legacy-fallback
        // path -- exactly the kind of direct write this project's Phase
        // 3B.1/3B.2 consolidation eliminated everywhere else, so this test
        // must not reintroduce it). No delegation cleanup performed here --
        // this must self-heal purely via live re-verification.
        $assignment = UserAssignment::where('user_id', $this->director()->id)->firstOrFail();
        app(GovernanceAssignmentService::class)->updateFromCanonicalPayload($assignment, [
            'user_id' => $assignment->user_id,
            'department_id' => $assignment->department_id,
            'responsibility_id' => $assignment->responsibility_id,
            'scope_type' => $assignment->scope_type,
            'is_primary' => $assignment->is_primary,
            'is_active' => false,
        ], $this->director()->id);

        $this->assertFalse(app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->odA->id));
        $this->assertNotNull(Delegation::find($delegation->id), 'The delegation row itself must still exist -- it just stops granting anything.');
    }

    // ---------------- Section 36: permanent access unchanged ----------------

    public function test_delegation_never_mutates_roles_permissions_or_assignments(): void
    {
        $this->giveDirectorOrganizationWideAuthority();

        $role = Role::firstOrCreate(['name' => 'PHPUnit Delegation Untouched Role', 'guard_name' => 'web']);
        $permission = Permission::firstOrCreate(['name' => 'phpunit_delegation_untouched_permission', 'guard_name' => 'web']);
        $this->deputy()->syncRoles([$role->id]);
        $this->deputy()->syncPermissions([]);

        $deputyRolesBefore = $this->deputy()->roles()->pluck('roles.id')->sort()->values()->all();
        $deputyPermsBefore = $this->deputy()->getDirectPermissions()->pluck('id')->sort()->values()->all();
        $deputyAssignmentsBefore = UserAssignment::where('user_id', $this->deputy()->id)->pluck('id')->sort()->values()->all();
        $directorRolesBefore = $this->director()->roles()->pluck('roles.id')->sort()->values()->all();
        $directorAssignmentsBefore = UserAssignment::where('user_id', $this->director()->id)->pluck('id')->sort()->values()->all();

        $delegation = $this->delegate();
        $instance = $this->makeInstance($this->leaveLikeDefinition);
        app(AccessControlService::class)->canApprove($this->deputy()->fresh(), $instance, $this->odA->id);
        app(DelegationService::class)->revoke($delegation, $this->director()->id);

        $this->deputy()->unsetRelation('roles');
        $this->director()->unsetRelation('roles');

        $this->assertSame($deputyRolesBefore, $this->deputy()->roles()->pluck('roles.id')->sort()->values()->all());
        $this->assertSame($deputyPermsBefore, $this->deputy()->getDirectPermissions()->pluck('id')->sort()->values()->all());
        $this->assertSame($deputyAssignmentsBefore, UserAssignment::where('user_id', $this->deputy()->id)->pluck('id')->sort()->values()->all());
        $this->assertSame($directorRolesBefore, $this->director()->roles()->pluck('roles.id')->sort()->values()->all());
        $this->assertSame($directorAssignmentsBefore, UserAssignment::where('user_id', $this->director()->id)->pluck('id')->sort()->values()->all());
    }

    // ---------------- Section 37: multi-unit ----------------

    public function test_unit_scope_grants_only_the_single_unit(): void
    {
        $this->giveDirectorOrganizationWideAuthority();
        $this->delegate([
            'scope_type' => UserAssignment::SCOPE_SELF_UNIT_ONLY,
            'scope_department_ids' => [$this->odA->id],
        ]);
        $instance = $this->makeInstance($this->leaveLikeDefinition);

        // self_unit_only expands to sibling units of the same type under the
        // same parent (OD A and OD B are both children of PHD) -- so both
        // are granted, but a grandchild (HC A1) is not.
        $this->assertTrue(app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->odA->id));
        $this->assertFalse(app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->hcA1->id));
    }

    public function test_unit_tree_scope_grants_self_and_all_descendants(): void
    {
        $this->giveDirectorOrganizationWideAuthority();
        $this->delegate([
            'scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
            'scope_department_ids' => [$this->odA->id],
        ]);
        $instance = $this->makeInstance($this->leaveLikeDefinition);

        $this->assertTrue(app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->odA->id));
        $this->assertTrue(app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->hcA1->id));
        $this->assertTrue(app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->hcA2->id));
        $this->assertFalse(app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->odB->id));
    }

    public function test_selected_units_scope_grants_exactly_the_selected_branches(): void
    {
        $this->giveDirectorOrganizationWideAuthority();
        $this->delegate([
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
            'scope_department_ids' => [$this->hcA1->id, $this->hcB1->id],
        ]);
        $instance = $this->makeInstance($this->leaveLikeDefinition);

        $this->assertTrue(app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->hcA1->id));
        $this->assertTrue(app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->hcB1->id));
        $this->assertFalse(app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->hcA2->id));
        $this->assertFalse(app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->odA->id));
    }

    public function test_organization_scope_grants_every_unit(): void
    {
        $this->giveDirectorOrganizationWideAuthority();
        $this->delegate([
            'scope_type' => UserAssignment::SCOPE_ALL,
            'scope_department_ids' => [],
        ]);
        $instance = $this->makeInstance($this->leaveLikeDefinition);

        $this->assertTrue(app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->odA->id));
        $this->assertTrue(app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->odB->id));
        $this->assertTrue(app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->hcB1->id));
    }

    public function test_delegatee_native_scope_is_not_unioned_with_delegated_scope(): void
    {
        $this->giveDirectorOrganizationWideAuthority();
        // Deputy natively holds an UNRELATED responsibility-free scope at OD B
        // (a different responsibility, so it does not make them a native
        // actor for the leave-like step at all) -- proves the delegated
        // grant does not accidentally combine with whatever the delegatee
        // already has natively.
        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $this->deputy()->id,
            'department_id' => $this->odB->id,
            'responsibility_id' => 3, // 'manager', unrelated to this workflow step's responsibility (1 = head)
            'scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
            'is_primary' => false,
            'is_active' => true,
        ]);
        $this->delegate([
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
            'scope_department_ids' => [$this->hcA1->id],
        ]);
        $instance = $this->makeInstance($this->leaveLikeDefinition);

        $this->assertTrue(app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->hcA1->id));
        $this->assertFalse(
            app(AccessControlService::class)->canApprove($this->deputy(), $instance, $this->odB->id),
            'Deputy\'s own unrelated native OD B assignment must not leak into this delegated approval capability.'
        );
    }

    // ---------------- Section 38: no authority leakage ----------------

    public function test_delegating_one_module_does_not_grant_unrelated_authority(): void
    {
        $this->giveDirectorOrganizationWideAuthority();
        $this->delegate(); // phpunit_leave_like only

        $missionInstance = $this->makeInstance($this->missionLikeDefinition);
        $this->assertFalse(app(AccessControlService::class)->canApprove($this->deputy(), $missionInstance, $this->odA->id));

        // No generic org-scope leak either: Deputy still resolves to no
        // effective org scope of their own via the canonical resolver.
        $this->assertSame([], app(\Modules\HumanResource\Support\OrgHierarchyAccessService::class)->managedBranchIds($this->deputy()));

        // No Spatie role/permission leak.
        $this->assertFalse($this->deputy()->fresh()->hasRole('Super Admin'));
        $this->assertCount(0, $this->deputy()->fresh()->getDirectPermissions());
    }
}
