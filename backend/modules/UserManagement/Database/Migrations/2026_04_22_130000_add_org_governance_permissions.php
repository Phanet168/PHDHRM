<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

        $menuId = null;
        if (Schema::hasTable('per_menus')) {
            $menuId = DB::table('per_menus')
                ->where('menu_name', 'Department')
                ->value('id');

            if (!$menuId) {
                $now = now();
                $menuId = DB::table('per_menus')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'parentmenu_id' => null,
                    'lable' => 0,
                    'menu_name' => 'Org Governance',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (!$menuId) {
            return;
        }

        $permissionNames = [
            'read_org_governance',
            'create_org_governance',
            'update_org_governance',
            'delete_org_governance',
        ];

        $permissionIds = [];
        foreach ($permissionNames as $name) {
            $existingId = DB::table($permissionsTable)
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->value('id');

            if ($existingId) {
                DB::table($permissionsTable)
                    ->where('id', $existingId)
                    ->update([
                        'per_menu_id' => $menuId,
                        'updated_at' => now(),
                    ]);
                $permissionIds[] = (int) $existingId;
                continue;
            }

            $permissionIds[] = (int) DB::table($permissionsTable)->insertGetId([
                'name' => $name,
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

        if ($superAdminRoleId) {
            foreach ($permissionIds as $permissionId) {
                $exists = DB::table($roleHasPermissionsTable)
                    ->where('permission_id', $permissionId)
                    ->where('role_id', (int) $superAdminRoleId)
                    ->exists();

                if (!$exists) {
                    DB::table($roleHasPermissionsTable)->insert([
                        'permission_id' => $permissionId,
                        'role_id' => (int) $superAdminRoleId,
                    ]);
                }
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

        $permissionNames = [
            'read_org_governance',
            'create_org_governance',
            'update_org_governance',
            'delete_org_governance',
        ];

        $permissionIds = DB::table($permissionsTable)
            ->whereIn('name', $permissionNames)
            ->where('guard_name', 'web')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (!empty($permissionIds)) {
            DB::table($roleHasPermissionsTable)
                ->whereIn('permission_id', $permissionIds)
                ->delete();

            DB::table($permissionsTable)
                ->whereIn('id', $permissionIds)
                ->delete();
        }

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};

