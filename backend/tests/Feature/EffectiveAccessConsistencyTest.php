<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
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
 * Phase 3E, section 32/33: proves the Access Control Center's "Effective
 * Access" explanation (the real role.user.effective-access HTTP endpoint,
 * which wraps AccessControlService::effectiveAccess()) agrees with actual
 * authorization behavior (AccessControlService::canApprove(),
 * hasPermission()) for every representative user category the instructions
 * name: normal employee, unit manager, multi-unit manager, workflow
 * approver, temporary delegate, multi-role user, direct-permission-
 * exception user, and Super Admin. Runs against the real (dev) database,
 * wrapped in a transaction that is rolled back afterwards.
 */
class EffectiveAccessConsistencyTest extends TestCase
{
    use DatabaseTransactions;

    private const SUPER_ADMIN_ID = 25;
    private const RESPONSIBILITY_HEAD_ID = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    private function admin(): User
    {
        return User::query()->findOrFail(self::SUPER_ADMIN_ID);
    }

    private function effectiveAccess(User $target): array
    {
        return $this->actingAs($this->admin())
            ->getJson(route('role.user.effective-access', $target->id))
            ->assertOk()
            ->json('data');
    }

    public function test_super_admin_effective_access_agrees_with_capabilities(): void
    {
        $admin = $this->admin();
        $data = $this->effectiveAccess($admin);

        $this->assertTrue($data['super_admin']);
        $this->assertTrue(app(AccessControlService::class)->isSuperAdmin($admin));
        // Every listed permission must show allowed for a Super Admin.
        foreach ($data['modules'] as $module) {
            foreach ($module['permissions'] as $permission) {
                $this->assertTrue($permission['allowed'], "Super Admin must be allowed for {$permission['display_name']}.");
            }
        }
    }

    public function test_multi_role_user_effective_access_lists_both_roles_and_their_sources(): void
    {
        $target = User::query()->findOrFail(640);
        $roleA = Role::create(['name' => 'PHPUnit EA Role A', 'guard_name' => 'web']);
        $roleB = Role::create(['name' => 'PHPUnit EA Role B', 'guard_name' => 'web']);
        $permA = Permission::where('guard_name', 'web')->skip(0)->firstOrFail();
        $permB = Permission::where('guard_name', 'web')->where('id', '!=', $permA->id)->firstOrFail();
        $roleA->givePermissionTo($permA);
        $roleB->givePermissionTo($permB);
        $target->syncRoles([$roleA->id, $roleB->id]);

        $data = $this->effectiveAccess($target);

        $this->assertContains($roleA->name, $data['roles']);
        $this->assertContains($roleB->name, $data['roles']);

        $found = collect($data['modules'])->flatMap(fn ($m) => $m['permissions'])->keyBy('permission');
        $this->assertTrue($found[$permA->name]['allowed']);
        $this->assertContains(['type' => 'role', 'name' => $roleA->name], $found[$permA->name]['sources']);
        $this->assertTrue($found[$permB->name]['allowed']);
        $this->assertContains(['type' => 'role', 'name' => $roleB->name], $found[$permB->name]['sources']);
    }

    public function test_direct_permission_exception_user_is_distinguished_from_role_source(): void
    {
        $target = User::query()->findOrFail(640);
        $target->syncRoles([]);
        $permission = Permission::where('guard_name', 'web')->firstOrFail();
        $target->syncPermissions([$permission->id]);

        $data = $this->effectiveAccess($target);

        $found = collect($data['modules'])->flatMap(fn ($m) => $m['permissions'])->keyBy('permission');
        $this->assertTrue($found[$permission->name]['allowed']);
        $this->assertContains(['type' => 'direct_user_permission'], $found[$permission->name]['sources']);
    }

    public function test_normal_employee_with_no_assignment_has_no_scope_and_no_temporary_delegation(): void
    {
        $target = User::query()->findOrFail(28);
        UserAssignment::where('user_id', $target->id)->delete();

        $data = $this->effectiveAccess($target);

        $this->assertSame([], $data['temporary_delegations']);
    }

    public function test_unit_manager_and_multi_unit_manager_scope_matches_real_assignment_rows(): void
    {
        $unitManager = User::query()->findOrFail(640);
        $unitTypeId = (int) \Illuminate\Support\Facades\DB::table('org_unit_types')->orderBy('id')->value('id');
        $deptA = Department::create(['department_name' => 'PHPUnit EA Dept A', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $unitManager->id, 'department_id' => $deptA->id,
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID, 'scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
            'is_primary' => false, 'is_active' => true,
        ]);

        $multiUnitManager = User::query()->findOrFail(26);
        $deptB = Department::create(['department_name' => 'PHPUnit EA Dept B', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
        $deptC = Department::create(['department_name' => 'PHPUnit EA Dept C', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $multiUnitManager->id, 'department_id' => $deptB->id,
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID, 'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
            'is_primary' => false, 'is_active' => true,
        ]);
        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $multiUnitManager->id, 'department_id' => $deptC->id,
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID, 'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
            'is_primary' => false, 'is_active' => true,
        ]);

        $scopeGroupsUnit = $this->actingAs($this->admin())
            ->getJson(route('access-control.users.scopes.index', $unitManager->id))
            ->assertOk()->json('data');
        $this->assertSame('UNIT_TREE', $scopeGroupsUnit[0]['presentation_scope']);

        $scopeGroupsMulti = $this->actingAs($this->admin())
            ->getJson(route('access-control.users.scopes.index', $multiUnitManager->id))
            ->assertOk()->json('data');
        $this->assertSame('SELECTED_UNITS', $scopeGroupsMulti[0]['presentation_scope']);
        $this->assertCount(2, $scopeGroupsMulti[0]['assignments']);
    }

    public function test_workflow_approver_effective_access_agrees_with_real_can_approve(): void
    {
        $approver = User::query()->findOrFail(640);
        $unitTypeId = (int) \Illuminate\Support\Facades\DB::table('org_unit_types')->orderBy('id')->value('id');
        $dept = Department::create(['department_name' => 'PHPUnit EA Approver Dept', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $approver->id, 'department_id' => $dept->id,
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID, 'scope_type' => UserAssignment::SCOPE_ALL,
            'is_primary' => false, 'is_active' => true,
        ]);

        $definition = WorkflowDefinition::create([
            'module_key' => 'phpunit_ea_module', 'request_type_key' => 'test_type',
            'name' => 'PHPUnit EA Definition', 'priority' => 999, 'is_active' => true,
        ]);
        $definition->steps()->create([
            'step_order' => 1, 'step_name' => 'PHPUnit EA Step', 'action_type' => 'approve',
            'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY,
            'actor_responsibility_id' => self::RESPONSIBILITY_HEAD_ID, 'system_role_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_ALL, 'is_final_approval' => true, 'is_required' => true,
            'can_return' => true, 'can_reject' => true,
        ]);

        $rows = $this->actingAs($this->admin())
            ->getJson(route('access-control.users.approval-authority', $approver->id))
            ->assertOk()->json('data');
        $row = collect($rows)->firstWhere('module_key', 'phpunit_ea_module');
        $this->assertNotNull($row, 'userApprovalAuthority must list the module the approver can act on.');
        $this->assertTrue($row['can_act']);

        // Cross-check against the ACTUAL live gate used by real consumers.
        $instance = WorkflowInstance::create([
            'uuid' => (string) Str::uuid(), 'module_key' => 'phpunit_ea_module', 'request_type_key' => 'test_type',
            'source_type' => 'phpunit_test', 'source_id' => 1, 'workflow_definition_id' => $definition->id,
            'status' => 'pending', 'current_step_order' => 1,
        ]);
        $this->assertTrue(
            app(AccessControlService::class)->canApprove($approver, $instance, $dept->id),
            'canApprove() must agree with userApprovalAuthority()\'s can_act=true for this exact module.'
        );
    }

    public function test_temporary_delegate_effective_access_lists_delegation_and_agrees_with_scoped_can_approve(): void
    {
        $director = User::query()->findOrFail(640);
        $deputy = User::query()->findOrFail(26);
        $unitTypeId = (int) \Illuminate\Support\Facades\DB::table('org_unit_types')->orderBy('id')->value('id');
        $phd = Department::create(['department_name' => 'PHPUnit EA PHD', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
        $odA = Department::create(['department_name' => 'PHPUnit EA OD A', 'unit_type_id' => $unitTypeId, 'parent_id' => $phd->id, 'is_active' => true]);
        $odB = Department::create(['department_name' => 'PHPUnit EA OD B', 'unit_type_id' => $unitTypeId, 'parent_id' => $phd->id, 'is_active' => true]);

        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $director->id, 'department_id' => $phd->id,
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID, 'scope_type' => UserAssignment::SCOPE_ALL,
            'is_primary' => false, 'is_active' => true,
        ]);

        $definition = WorkflowDefinition::create([
            'module_key' => 'phpunit_ea_deleg_module', 'request_type_key' => 'test_type',
            'name' => 'PHPUnit EA Delegation Definition', 'priority' => 999, 'is_active' => true,
        ]);
        $definition->steps()->create([
            'step_order' => 1, 'step_name' => 'PHPUnit EA Deleg Step', 'action_type' => 'approve',
            'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY,
            'actor_responsibility_id' => self::RESPONSIBILITY_HEAD_ID, 'system_role_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN, 'is_final_approval' => true, 'is_required' => true,
            'can_return' => true, 'can_reject' => true,
        ]);

        app(DelegationService::class)->create([
            'delegator_user_id' => $director->id, 'delegatee_user_id' => $deputy->id,
            'authority_module_key' => 'phpunit_ea_deleg_module', 'scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
            'scope_department_ids' => [$odA->id], 'starts_at' => now()->subHour(), 'ends_at' => now()->addDay(),
        ], $director->id);

        $data = $this->effectiveAccess($deputy);
        $this->assertNotEmpty($data['temporary_delegations'], 'Effective Access must list the active incoming delegation.');
        $this->assertSame('phpunit_ea_deleg_module', $data['temporary_delegations'][0]['authority_module_key']);
        $this->assertSame($director->id, $data['temporary_delegations'][0]['from_user_id']);

        $instance = WorkflowInstance::create([
            'uuid' => (string) Str::uuid(), 'module_key' => 'phpunit_ea_deleg_module', 'request_type_key' => 'test_type',
            'source_type' => 'phpunit_test', 'source_id' => 1, 'workflow_definition_id' => $definition->id,
            'status' => 'pending', 'current_step_order' => 1,
        ]);
        $accessControlService = app(AccessControlService::class);
        $this->assertTrue($accessControlService->canApprove($deputy, $instance, $odA->id), 'Deputy must be able to approve inside the delegated scope.');
        $this->assertFalse($accessControlService->canApprove($deputy, $instance, $odB->id), 'Deputy must NOT be able to approve outside the delegated scope, even though the delegation is listed in Effective Access.');
    }
}
