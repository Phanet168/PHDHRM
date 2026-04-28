<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names', []);
        $permissionsTable = $tableNames['permissions'] ?? 'permissions';
        $rolesTable = $tableNames['roles'] ?? 'roles';
        $roleHasPermissionsTable = $tableNames['role_has_permissions'] ?? 'role_has_permissions';

        if (!Schema::hasTable($permissionsTable) || !Schema::hasTable($rolesTable) || !Schema::hasTable($roleHasPermissionsTable)) {
            return;
        }

        if (!Schema::hasTable('per_menus')) {
            return;
        }

        $menuId = DB::table('per_menus')
            ->where('menu_name', 'Correspondence Management')
            ->value('id');

        if (!$menuId) {
            return;
        }

        $permissionName = 'setting_correspondence_management';

        $permissionId = DB::table($permissionsTable)
            ->where('name', $permissionName)
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId) {
            DB::table($permissionsTable)
                ->where('id', $permissionId)
                ->update([
                    'per_menu_id' => $menuId,
                    'updated_at' => now(),
                ]);
        } else {
            $permissionId = (int) DB::table($permissionsTable)->insertGetId([
                'name' => $permissionName,
                'guard_name' => 'web',
                'per_menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $superAdminRoleId = DB::table($rolesTable)
            ->where('name', 'Super Admin')
            ->where('guard_name', 'web')
            ->value('id');

        if ($superAdminRoleId && $permissionId) {
            $exists = DB::table($roleHasPermissionsTable)
                ->where('permission_id', (int) $permissionId)
                ->where('role_id', (int) $superAdminRoleId)
                ->exists();

            if (!$exists) {
                DB::table($roleHasPermissionsTable)->insert([
                    'permission_id' => (int) $permissionId,
                    'role_id' => (int) $superAdminRoleId,
                ]);
            }
        }

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names', []);
        $permissionsTable = $tableNames['permissions'] ?? 'permissions';
        $roleHasPermissionsTable = $tableNames['role_has_permissions'] ?? 'role_has_permissions';

        if (!Schema::hasTable($permissionsTable) || !Schema::hasTable($roleHasPermissionsTable)) {
            return;
        }

        $permissionId = DB::table($permissionsTable)
            ->where('name', 'setting_correspondence_management')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId) {
            DB::table($roleHasPermissionsTable)
                ->where('permission_id', (int) $permissionId)
                ->delete();

            DB::table($permissionsTable)
                ->where('id', (int) $permissionId)
                ->delete();
        }

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};
