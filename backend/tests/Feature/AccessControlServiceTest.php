<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Support\Facades\DB;
use Modules\HumanResource\Entities\UserAssignment;
use Tests\Support\BuildsAccessControlDatabase;
use Tests\TestCase;

/**
 * Focused tests for the Phase 2 central authorization foundation
 * (AccessControlService + PermissionCatalogService). Never touches the
 * application's real database (see BuildsAccessControlDatabase).
 */
class AccessControlServiceTest extends TestCase
{
    use BuildsAccessControlDatabase;

    private AccessControlService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAccessControlDatabase();
        $this->service = app(AccessControlService::class);
    }

    public function test_super_admin_via_user_type_id_is_detected(): void
    {
        $user = $this->makeAccessControlUser(['user_type_id' => 1]);
        $this->assertTrue($this->service->isSuperAdmin($user));
        $this->assertTrue($this->service->hasPermission($user, 'anything_not_granted'));
    }

    public function test_regular_user_is_not_super_admin_and_permission_denied_by_default(): void
    {
        $user = $this->makeAccessControlUser();
        $this->assertFalse($this->service->isSuperAdmin($user));
        $this->assertFalse($this->service->hasPermission($user, 'read_employee'));
        $this->assertFalse($this->service->hasAnyPermission($user, ['read_employee', 'update_employee']));
    }

    public function test_multiple_roles_union_into_effective_permissions(): void
    {
        $user = $this->makeAccessControlUser();
        $roleA = $this->makeAccessControlRole('Accountant');
        $roleB = $this->makeAccessControlRole('Payroll Approver');
        $permView = $this->makeAccessControlPermission('read_employee');
        $permApprove = $this->makeAccessControlPermission('approve_payroll');

        DB::table('role_has_permissions')->insert([
            ['permission_id' => $permView, 'role_id' => $roleA],
            ['permission_id' => $permApprove, 'role_id' => $roleB],
        ]);
        DB::table('model_has_roles')->insert([
            ['role_id' => $roleA, 'model_type' => User::class, 'model_id' => $user->id],
            ['role_id' => $roleB, 'model_type' => User::class, 'model_id' => $user->id],
        ]);

        $this->assertEqualsCanonicalizing(['Accountant', 'Payroll Approver'], $this->service->roleNames($user));
        $this->assertTrue($this->service->hasPermission($user, 'read_employee'));
        $this->assertTrue($this->service->hasPermission($user, 'approve_payroll'));
        $this->assertEqualsCanonicalizing(
            ['read_employee', 'approve_payroll'],
            $this->service->effectivePermissionNames($user)
        );
    }

    public function test_direct_user_permission_is_distinguished_from_role_permission_in_explain_access(): void
    {
        $user = $this->makeAccessControlUser();
        $role = $this->makeAccessControlRole('Accountant');
        $rolePerm = $this->makeAccessControlPermission('read_employee');
        $directPerm = $this->makeAccessControlPermission('payroll.salary.export');

        DB::table('role_has_permissions')->insert(['permission_id' => $rolePerm, 'role_id' => $role]);
        DB::table('model_has_roles')->insert(['role_id' => $role, 'model_type' => User::class, 'model_id' => $user->id]);
        DB::table('model_has_permissions')->insert(['permission_id' => $directPerm, 'model_type' => User::class, 'model_id' => $user->id]);

        $roleExplain = $this->service->explainAccess($user, 'read_employee');
        $this->assertTrue($roleExplain['allowed']);
        $this->assertSame([['type' => 'role', 'name' => 'Accountant']], $roleExplain['sources']);

        $directExplain = $this->service->explainAccess($user, 'payroll.salary.export');
        $this->assertTrue($directExplain['allowed']);
        $this->assertSame([['type' => 'direct_user_permission']], $directExplain['sources']);

        $deniedExplain = $this->service->explainAccess($user, 'delete_employee');
        $this->assertFalse($deniedExplain['allowed']);
        $this->assertSame([], $deniedExplain['sources']);
    }

    public function test_super_admin_explain_access_reports_super_admin_source(): void
    {
        $user = $this->makeAccessControlUser(['user_type_id' => 1]);
        $explain = $this->service->explainAccess($user, 'anything_at_all');
        $this->assertTrue($explain['allowed']);
        $this->assertSame([['type' => 'super_admin']], $explain['sources']);
        $this->assertSame('all', $explain['scope']['type']);
        $this->assertNull($explain['scope']['unit_ids']);
    }

    public function test_scope_resolution_reflects_primary_user_assignment(): void
    {
        $user = $this->makeAccessControlUser();
        $responsibilityId = DB::table('system_roles')->insertGetId([
            'code' => 'unit_head', 'name' => 'Unit Head', 'created_at' => now(), 'updated_at' => now(),
        ]);
        UserAssignment::query()->create([
            'user_id' => $user->id,
            'department_id' => 12,
            'responsibility_id' => $responsibilityId,
            'scope_type' => UserAssignment::SCOPE_SELF_ONLY,
            'is_primary' => true,
            'is_active' => true,
        ]);

        $scope = $this->service->resolveScope($user);
        $this->assertSame(UserAssignment::SCOPE_SELF_ONLY, $scope['type']);
        $this->assertSame(12, $scope['unit_id']);
        $this->assertSame([12], $scope['unit_ids']);

        $this->assertTrue($this->service->canAccessUnit($user, 12));
        $this->assertFalse($this->service->canAccessUnit($user, 99));
    }

    public function test_scope_all_grants_access_to_any_unit(): void
    {
        $user = $this->makeAccessControlUser();
        $responsibilityId = DB::table('system_roles')->insertGetId([
            'code' => 'director', 'name' => 'Director', 'created_at' => now(), 'updated_at' => now(),
        ]);
        UserAssignment::query()->create([
            'user_id' => $user->id,
            'department_id' => 5,
            'responsibility_id' => $responsibilityId,
            'scope_type' => UserAssignment::SCOPE_ALL,
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->assertNull($this->service->resolveScope($user)['unit_ids']);
        $this->assertTrue($this->service->canAccessUnit($user, 999));
    }

    public function test_capabilities_read_model_groups_permissions_by_module_and_action(): void
    {
        $user = $this->makeAccessControlUser();
        $role = $this->makeAccessControlRole('HR Officer');
        $perm = $this->makeAccessControlPermission('read_employee');
        DB::table('role_has_permissions')->insert(['permission_id' => $perm, 'role_id' => $role]);
        DB::table('model_has_roles')->insert(['role_id' => $role, 'model_type' => User::class, 'model_id' => $user->id]);

        $capabilities = $this->service->capabilities($user);

        $this->assertFalse($capabilities['super_admin']);
        $this->assertSame(['HR Officer'], $capabilities['roles']);
        $this->assertContains('read_employee', $capabilities['permissions']);
        $this->assertTrue($capabilities['modules']['human_resource']['view'] ?? false);
    }
}
