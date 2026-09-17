<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature tests for the Phase 3A Access Control Center. Runs against the
 * project's real (dev) database, but every test is wrapped in a database
 * transaction that is rolled back afterwards -- nothing written here
 * persists. Known real users/roles are only ever read, never mutated
 * in place; anything mutated is a throwaway role created by the test itself.
 */
class AccessControlCenterTest extends TestCase
{
    use DatabaseTransactions;

    private const SUPER_ADMIN_USER_ID = 25;
    private const NON_ADMIN_USER_ID = 640;

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

    // ---------------- Access / authorization ----------------

    public function test_authorized_admin_can_view_the_access_control_center(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('access-control.index'))
            ->assertOk()
            ->assertSee('មជ្ឈមណ្ឌលគ្រប់គ្រងសិទ្ធិ', false);
    }

    public function test_unauthorized_user_cannot_view_the_access_control_center(): void
    {
        $this->actingAs($this->nonAdmin())
            ->get(route('access-control.index'))
            ->assertForbidden();
    }

    public function test_unauthorized_user_cannot_manage_roles(): void
    {
        $this->actingAs($this->nonAdmin())
            ->getJson(route('access-control.roles.index'))
            ->assertForbidden();

        $this->actingAs($this->nonAdmin())
            ->postJson(route('access-control.roles.index'), ['name' => 'Should Not Be Created'])
            ->assertForbidden();
    }

    public function test_unauthorized_user_cannot_manage_users(): void
    {
        $this->actingAs($this->nonAdmin())
            ->getJson(route('access-control.users.search'), ['q' => 'a'])
            ->assertForbidden();

        $this->actingAs($this->nonAdmin())
            ->putJson(route('access-control.users.roles.update', $this->nonAdmin()->id), ['role_ids' => []])
            ->assertForbidden();
    }

    // ---------------- Role management ----------------

    public function test_authorized_admin_can_view_role_list(): void
    {
        $this->actingAs($this->superAdmin())
            ->getJson(route('access-control.roles.index'))
            ->assertOk()
            ->assertJsonStructure(['status', 'data' => [['id', 'name', 'user_count', 'protected']]]);
    }

    public function test_super_admin_role_is_reported_as_protected(): void
    {
        $superAdminRole = Role::where('name', 'Super Admin')->where('guard_name', 'web')->firstOrFail();

        $this->actingAs($this->superAdmin())
            ->getJson(route('access-control.roles.show', $superAdminRole->id))
            ->assertOk()
            ->assertJsonPath('data.protected', true);
    }

    public function test_protected_role_permissions_cannot_be_changed(): void
    {
        $superAdminRole = Role::where('name', 'Super Admin')->where('guard_name', 'web')->firstOrFail();

        $this->actingAs($this->superAdmin())
            ->putJson(route('access-control.roles.permissions.update', $superAdminRole->id), ['permission_ids' => []])
            ->assertStatus(422);
    }

    public function test_role_permission_update_works_and_ignores_invalid_permission_ids(): void
    {
        $admin = $this->superAdmin();
        $role = Role::create(['name' => 'PHPUnit Temp Role A', 'guard_name' => 'web']);
        $realPermission = Permission::where('guard_name', 'web')->firstOrFail();
        $bogusId = 999999999;

        $this->actingAs($admin)
            ->putJson(route('access-control.roles.permissions.update', $role->id), [
                'permission_ids' => [$realPermission->id, $bogusId],
            ])
            ->assertOk()
            ->assertJsonPath('data.permission_ids', [$realPermission->id]);

        $this->assertTrue($role->fresh()->hasPermissionTo($realPermission->name));
    }

    public function test_multiple_roles_are_unaffected_by_editing_one_role(): void
    {
        $admin = $this->superAdmin();
        $roleA = Role::create(['name' => 'PHPUnit Temp Role B', 'guard_name' => 'web']);
        $roleB = Role::create(['name' => 'PHPUnit Temp Role C', 'guard_name' => 'web']);
        $permission = Permission::where('guard_name', 'web')->firstOrFail();
        $roleB->givePermissionTo($permission);

        $this->actingAs($admin)
            ->putJson(route('access-control.roles.permissions.update', $roleA->id), [
                'permission_ids' => [$permission->id],
            ])
            ->assertOk();

        $this->assertTrue($roleB->fresh()->hasPermissionTo($permission->name), 'Editing role A must not affect role B.');
    }

    public function test_role_with_assigned_users_cannot_be_deleted(): void
    {
        $admin = $this->superAdmin();
        $role = Role::create(['name' => 'PHPUnit Temp Role D', 'guard_name' => 'web']);
        $this->nonAdmin()->assignRole($role);

        $this->actingAs($admin)
            ->deleteJson(route('access-control.roles.destroy', $role->id))
            ->assertStatus(422);

        $this->assertNotNull(Role::find($role->id));
    }

    public function test_role_without_users_can_be_deleted(): void
    {
        $admin = $this->superAdmin();
        $role = Role::create(['name' => 'PHPUnit Temp Role E', 'guard_name' => 'web']);

        $this->actingAs($admin)
            ->deleteJson(route('access-control.roles.destroy', $role->id))
            ->assertOk();

        $this->assertNull(Role::find($role->id));
    }

    // ---------------- User management ----------------

    public function test_role_assignment_and_removal_preserve_other_roles(): void
    {
        $admin = $this->superAdmin();
        $target = $this->nonAdmin();
        $roleA = Role::create(['name' => 'PHPUnit Temp Role F', 'guard_name' => 'web']);
        $roleB = Role::create(['name' => 'PHPUnit Temp Role G', 'guard_name' => 'web']);
        $originalRoleIds = $target->roles()->pluck('roles.id')->all();

        // Assign two roles on top of whatever the user already has.
        $this->actingAs($admin)
            ->putJson(route('access-control.users.roles.update', $target->id), [
                'role_ids' => array_merge($originalRoleIds, [$roleA->id, $roleB->id]),
            ])
            ->assertOk();

        $target->refresh();
        $this->assertTrue($target->hasRole($roleA->name));
        $this->assertTrue($target->hasRole($roleB->name));
        foreach ($originalRoleIds as $id) {
            $this->assertTrue($target->roles()->where('roles.id', $id)->exists(), 'Original role must be preserved.');
        }

        // Remove just roleA, keep roleB and originals.
        $this->actingAs($admin)
            ->putJson(route('access-control.users.roles.update', $target->id), [
                'role_ids' => array_merge($originalRoleIds, [$roleB->id]),
            ])
            ->assertOk();

        $target->refresh();
        $this->assertFalse($target->hasRole($roleA->name));
        $this->assertTrue($target->hasRole($roleB->name));
        foreach ($originalRoleIds as $id) {
            $this->assertTrue($target->roles()->where('roles.id', $id)->exists());
        }
    }

    public function test_removing_super_admin_role_from_the_last_super_admin_is_blocked(): void
    {
        $admin = $this->superAdmin();
        // Guard: this scenario only makes sense if the acting admin IS the
        // only Super Admin holder (via role, not just user_type_id). If not,
        // skip rather than assert something false about this dev database.
        $superAdminRoleId = Role::where('name', 'Super Admin')->where('guard_name', 'web')->value('id');
        $holders = User::whereHas('roles', fn ($q) => $q->where('roles.id', $superAdminRoleId))->pluck('id')->all();
        $systemAdmins = User::where('user_type_id', 1)->pluck('id')->all();

        if (count($holders) !== 1 || $holders !== [self::SUPER_ADMIN_USER_ID] || !empty(array_diff($systemAdmins, [self::SUPER_ADMIN_USER_ID]))) {
            $this->markTestSkipped('Dev database does not currently have exactly one Super Admin holder; lockout-prevention scenario not applicable.');
        }

        $this->actingAs($admin)
            ->putJson(route('access-control.users.roles.update', $admin->id), ['role_ids' => []])
            ->assertStatus(422);

        $this->assertTrue($admin->fresh()->hasRole('Super Admin'));
    }

    public function test_direct_permission_assignment_and_removal(): void
    {
        $admin = $this->superAdmin();
        $target = $this->nonAdmin();
        $permission = Permission::where('guard_name', 'web')->firstOrFail();

        $this->actingAs($admin)
            ->putJson(route('access-control.users.direct-permissions.update', $target->id), [
                'permission_ids' => [$permission->id],
            ])
            ->assertOk();

        $this->assertTrue($target->fresh()->hasDirectPermission($permission->name));

        $this->actingAs($admin)
            ->putJson(route('access-control.users.direct-permissions.update', $target->id), [
                'permission_ids' => [],
            ])
            ->assertOk();

        $this->assertFalse($target->fresh()->hasDirectPermission($permission->name));
    }

    public function test_user_show_returns_identity_roles_scope_and_super_admin_flag(): void
    {
        $this->actingAs($this->superAdmin())
            ->getJson(route('access-control.users.show', self::SUPER_ADMIN_USER_ID))
            ->assertOk()
            ->assertJsonPath('data.super_admin', true)
            ->assertJsonStructure(['data' => ['roles', 'direct_permissions', 'scope', 'effective_permission_count']]);
    }

    // ---------------- Effective Access (reuses the Phase 2 endpoint) ----------------

    public function test_effective_access_endpoint_reflects_role_and_direct_sources(): void
    {
        $admin = $this->superAdmin();
        $target = $this->nonAdmin();
        $role = Role::create(['name' => 'PHPUnit Temp Role H', 'guard_name' => 'web']);
        $rolePermission = Permission::where('guard_name', 'web')->where('name', '!=', 'read_dashboard')->skip(0)->firstOrFail();
        $directPermission = Permission::where('guard_name', 'web')->where('id', '!=', $rolePermission->id)->firstOrFail();
        $role->givePermissionTo($rolePermission);
        $target->assignRole($role);
        $target->givePermissionTo($directPermission);

        $response = $this->actingAs($admin)->getJson(route('role.user.effective-access', $target->id))->assertOk();
        $data = $response->json('data');

        $found = collect($data['modules'] ?? [])->flatMap(fn ($m) => $m['permissions'])->keyBy('permission');
        $this->assertTrue($found[$rolePermission->name]['allowed']);
        $this->assertContains(['type' => 'role', 'name' => $role->name], $found[$rolePermission->name]['sources']);
        $this->assertTrue($found[$directPermission->name]['allowed']);
        $this->assertContains(['type' => 'direct_user_permission'], $found[$directPermission->name]['sources']);
    }

    // ---------------- Backward compatibility ----------------

    public function test_capability_endpoint_still_works_after_phase3a(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*']);

        $this->getJson('/api/v1/me/capabilities')
            ->assertOk()
            ->assertJsonPath('data.super_admin', true);
    }
}
