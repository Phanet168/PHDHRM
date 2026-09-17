<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use App\Services\AccessControlAuditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Entities\WorkflowDefinition;
use Modules\HumanResource\Entities\WorkflowDefinitionStep;
use Modules\HumanResource\Services\GovernanceAssignmentService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 3E: proves Access Control Center governance changes are actually
 * captured in the unified Audit History feed, through the REAL write
 * endpoints (not by calling AccessControlAuditService directly) and read
 * back through the REAL read endpoint. Runs against the real (dev)
 * database, wrapped in a transaction that is rolled back afterwards.
 */
class AuditHistoryTest extends TestCase
{
    use DatabaseTransactions;

    private const SUPER_ADMIN_USER_ID = 25;
    private const NON_ADMIN_USER_ID = 640;
    private const RESPONSIBILITY_HEAD_ID = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    private function superAdmin(): User
    {
        return User::query()->findOrFail(self::SUPER_ADMIN_USER_ID);
    }

    private function nonAdmin(): User
    {
        return User::query()->findOrFail(self::NON_ADMIN_USER_ID);
    }

    private function fetchAuditRow(string $action, ?string $targetLabelContains = null): ?array
    {
        $response = $this->actingAs($this->superAdmin())
            ->getJson(route('access-control.audit-history.index', ['per_page' => 50]))
            ->assertOk();

        $rows = collect($response->json('data'));

        return $rows->first(function (array $row) use ($action, $targetLabelContains) {
            if ($row['action'] !== $action) {
                return false;
            }

            return $targetLabelContains === null || str_contains($row['target_label'], $targetLabelContains);
        });
    }

    public function test_role_created_appears_in_audit_history(): void
    {
        $admin = $this->superAdmin();
        $roleName = 'PHPUnit Audit Role ' . uniqid();

        $this->actingAs($admin)->postJson(route('access-control.roles.index'), ['name' => $roleName])->assertCreated();

        $row = $this->fetchAuditRow('role_created', $roleName);
        $this->assertNotNull($row, 'role_created must appear in audit history.');
        $this->assertSame('ROLE_PERMISSION', $row['category']);
        $this->assertSame($admin->id, $row['actor']['id']);
        $this->assertSame($roleName, $row['after']['name']);
    }

    public function test_role_permissions_updated_captures_before_and_after(): void
    {
        $admin = $this->superAdmin();
        $role = Role::create(['name' => 'PHPUnit Audit Perm Role', 'guard_name' => 'web']);
        $permission = Permission::where('guard_name', 'web')->firstOrFail();

        $this->actingAs($admin)
            ->putJson(route('access-control.roles.permissions.update', $role->id), ['permission_ids' => [$permission->id]])
            ->assertOk();

        $row = $this->fetchAuditRow('role_permissions_updated', $role->name);
        $this->assertNotNull($row);
        $this->assertSame([], $row['before']['permissions']);
        $this->assertContains($permission->name, $row['after']['permissions']);
    }

    public function test_role_deleted_appears_in_audit_history(): void
    {
        $admin = $this->superAdmin();
        $role = Role::create(['name' => 'PHPUnit Audit Delete Role', 'guard_name' => 'web']);

        $this->actingAs($admin)->deleteJson(route('access-control.roles.destroy', $role->id))->assertOk();

        $row = $this->fetchAuditRow('role_deleted', $role->name);
        $this->assertNotNull($row, 'role_deleted must appear in audit history even though the role row itself is gone.');
    }

    public function test_user_roles_updated_appears_in_audit_history(): void
    {
        $admin = $this->superAdmin();
        $target = $this->nonAdmin();
        $role = Role::create(['name' => 'PHPUnit Audit User Role', 'guard_name' => 'web']);
        $existingIds = $target->roles()->pluck('roles.id')->all();

        $this->actingAs($admin)
            ->putJson(route('access-control.users.roles.update', $target->id), [
                'role_ids' => array_merge($existingIds, [$role->id]),
            ])
            ->assertOk();

        $row = $this->fetchAuditRow('user_roles_updated', $target->full_name);
        $this->assertNotNull($row);
        $this->assertSame('USER_ACCESS', $row['category']);
        $this->assertSame($target->id, $row['target_user_id']);
        $this->assertContains($role->name, $row['after']['roles']);
    }

    public function test_user_direct_permissions_updated_appears_in_audit_history(): void
    {
        $admin = $this->superAdmin();
        $target = $this->nonAdmin();
        $permission = Permission::where('guard_name', 'web')->firstOrFail();

        $this->actingAs($admin)
            ->putJson(route('access-control.users.direct-permissions.update', $target->id), [
                'permission_ids' => [$permission->id],
            ])
            ->assertOk();

        $row = $this->fetchAuditRow('user_direct_permissions_updated', $target->full_name);
        $this->assertNotNull($row);
        $this->assertContains($permission->name, $row['after']['direct_permissions']);
    }

    public function test_organization_scope_updated_appears_in_audit_history(): void
    {
        $admin = $this->superAdmin();
        $target = $this->nonAdmin();
        $unitTypeId = (int) \Illuminate\Support\Facades\DB::table('org_unit_types')->orderBy('id')->value('id');
        $deptA = Department::create(['department_name' => 'PHPUnit Audit Dept A', 'unit_type_id' => $unitTypeId, 'is_active' => true]);
        $deptB = Department::create(['department_name' => 'PHPUnit Audit Dept B', 'unit_type_id' => $unitTypeId, 'is_active' => true]);

        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $target->id,
            'department_id' => $deptA->id,
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
            'is_primary' => false,
            'is_active' => true,
        ]);

        $groups = app(\App\Services\OrganizationScopeService::class)->assignmentGroups($target);
        $groupKey = $groups[0]['group_key'];

        $this->actingAs($admin)
            ->putJson(route('access-control.users.scopes.update', [$target->id, $groupKey]), [
                'scope_type' => 'SELECTED_UNITS',
                'department_ids' => [$deptA->id, $deptB->id],
            ])
            ->assertOk();

        $row = $this->fetchAuditRow('organization_scope_updated', $target->full_name);
        $this->assertNotNull($row);
        $this->assertSame('ORGANIZATION_SCOPE', $row['category']);
        $this->assertContains($deptB->department_name, $row['after']['units']);
    }

    public function test_approval_authority_updated_appears_in_audit_history(): void
    {
        $admin = $this->superAdmin();
        $definition = WorkflowDefinition::create([
            'module_key' => 'phpunit_audit_module',
            'request_type_key' => 'test_type',
            'name' => 'PHPUnit Audit Definition',
            'priority' => 999,
            'is_active' => true,
        ]);
        $definition->steps()->create([
            'step_order' => 1,
            'step_name' => 'Original Step',
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

        $this->actingAs($admin)
            ->putJson(route('access-control.approvals.update', $definition->id), [
                'name' => 'PHPUnit Audit Definition Renamed',
                'priority' => 999,
                'is_active' => true,
                'steps' => [[
                    'step_order' => 1,
                    'step_name' => 'Renamed Step',
                    'action_type' => 'approve',
                    'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY,
                    'actor_responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
                    'scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
                    'is_final_approval' => true,
                    'is_required' => true,
                    'can_return' => true,
                    'can_reject' => true,
                ]],
            ])
            ->assertOk();

        $row = $this->fetchAuditRow('approval_authority_updated');
        $this->assertNotNull($row);
        $this->assertSame('APPROVAL_AUTHORITY', $row['category']);
        $this->assertSame('PHPUnit Audit Definition', $row['before']['name']);
        $this->assertSame('PHPUnit Audit Definition Renamed', $row['after']['name']);
        $this->assertSame('Original Step', $row['before']['steps'][0]['step_name']);
        $this->assertSame('Renamed Step', $row['after']['steps'][0]['step_name']);
    }

    public function test_delegation_created_and_revoked_appear_in_audit_history_without_double_logging(): void
    {
        $director = $this->nonAdmin();
        $deputy = User::query()->findOrFail(26);
        $unitTypeId = (int) \Illuminate\Support\Facades\DB::table('org_unit_types')->orderBy('id')->value('id');
        $dept = Department::create(['department_name' => 'PHPUnit Audit Deleg Dept', 'unit_type_id' => $unitTypeId, 'is_active' => true]);

        app(GovernanceAssignmentService::class)->createFromCanonicalPayload([
            'user_id' => $director->id,
            'department_id' => $dept->id,
            'responsibility_id' => self::RESPONSIBILITY_HEAD_ID,
            'scope_type' => UserAssignment::SCOPE_ALL,
            'is_primary' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin())
            ->postJson(route('access-control.delegations.store'), [
                'delegator_user_id' => $director->id,
                'delegatee_user_id' => $deputy->id,
                'authority_module_key' => 'leave',
                'scope_type' => 'ORGANIZATION',
                'department_ids' => [$dept->id],
                'starts_at' => now()->subHour()->toDateTimeString(),
                'ends_at' => now()->addDay()->toDateTimeString(),
            ])
            ->assertCreated();

        $uuid = $response->json('data.uuid');

        $createdRow = $this->fetchAuditRow('delegation_created');
        $this->assertNotNull($createdRow, 'Delegation creation must appear in audit history (derived from the delegations table, not a duplicate log write).');
        $this->assertSame('DELEGATION', $createdRow['category']);
        $this->assertSame($deputy->id, $createdRow['target_user_id']);

        $this->actingAs($this->superAdmin())
            ->postJson(route('access-control.delegations.revoke', $uuid))
            ->assertOk();

        $revokedRow = $this->fetchAuditRow('delegation_revoked');
        $this->assertNotNull($revokedRow, 'Delegation revocation must appear in audit history.');

        // No duplicate writer: confirm the delegation's own lifecycle events
        // were never ALSO written into activity_log under log_name
        // 'access_control' -- they must come exclusively from the
        // delegations table itself.
        $delegationActivityCount = \Spatie\Activitylog\Models\Activity::query()
            ->where('log_name', AccessControlAuditService::LOG_NAME)
            ->whereIn('event', ['delegation_created', 'delegation_revoked'])
            ->count();
        $this->assertSame(0, $delegationActivityCount, 'Delegation events must be derived from the delegations table, never separately written to activity_log.');
    }

    public function test_audit_history_category_filter_narrows_results(): void
    {
        $admin = $this->superAdmin();
        $roleName = 'PHPUnit Audit Category Filter Role ' . uniqid();
        $this->actingAs($admin)->postJson(route('access-control.roles.index'), ['name' => $roleName])->assertCreated();

        $response = $this->actingAs($admin)
            ->getJson(route('access-control.audit-history.index', ['category' => 'DELEGATION', 'per_page' => 50]))
            ->assertOk();

        $rows = collect($response->json('data'));
        $this->assertFalse($rows->contains(fn (array $r) => $r['target_label'] === $roleName), 'Filtering to DELEGATION must exclude ROLE_PERMISSION rows.');
    }

    public function test_audit_history_actor_filter_narrows_results(): void
    {
        $admin = $this->superAdmin();
        $roleName = 'PHPUnit Audit Actor Filter Role ' . uniqid();
        $this->actingAs($admin)->postJson(route('access-control.roles.index'), ['name' => $roleName])->assertCreated();

        $response = $this->actingAs($admin)
            ->getJson(route('access-control.audit-history.index', ['actor_id' => $this->nonAdmin()->id, 'per_page' => 50]))
            ->assertOk();

        $rows = collect($response->json('data'));
        $this->assertFalse($rows->contains(fn (array $r) => $r['target_label'] === $roleName), 'Filtering to a different actor must exclude this admin\'s own action.');
    }

    public function test_unauthorized_user_cannot_view_audit_history(): void
    {
        // nonAdmin here intentionally holds no read_role_list/read_user_list
        // permission in a fresh throwaway state within this transaction.
        $plain = User::query()->findOrFail(28);
        $plain->syncPermissions([]);
        $plain->syncRoles([]);

        $this->actingAs($plain)
            ->getJson(route('access-control.audit-history.index'))
            ->assertForbidden();
    }
}
