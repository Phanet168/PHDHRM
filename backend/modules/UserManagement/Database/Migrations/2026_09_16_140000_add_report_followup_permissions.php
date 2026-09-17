<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Phase 1.5 follow-up (approved for Phase 2): creates the three permissions
 * identified as missing a suitable existing match for
 * sale_report_casher / CategoryWiseSalesReport / getResponseWarehouseWiseProductReport
 * (Modules\Report\Http\Controllers\ReportController).
 *
 * Per explicit instruction, this migration does NOT assign the new permissions
 * to any role automatically — see the Phase 2 report for which roles to consider.
 */
return new class extends Migration
{
    private array $permissionMenus = [
        'read_sale_report_casher' => 'Sale Report Casher',
        'read_category_wise_sales_report' => 'Category Wise Sales Report',
        'read_warehouse_wise_product' => 'Warehouse Wise Product Report',
    ];

    public function up(): void
    {
        $tableNames = config('permission.table_names', []);
        $permissionsTable = $tableNames['permissions'] ?? 'permissions';

        if (!Schema::hasTable($permissionsTable)) {
            return;
        }

        foreach ($this->permissionMenus as $name => $menuName) {
            $menuId = null;
            if (Schema::hasTable('per_menus')) {
                $menuId = DB::table('per_menus')->where('menu_name', $menuName)->value('id');

                if (!$menuId) {
                    $now = now();
                    $menuId = DB::table('per_menus')->insertGetId([
                        'uuid' => (string) Str::uuid(),
                        'parentmenu_id' => null,
                        'lable' => 0,
                        'menu_name' => $menuName,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $exists = DB::table($permissionsTable)
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table($permissionsTable)->insert([
                'name' => $name,
                'guard_name' => 'web',
                'per_menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
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
        $modelHasPermissionsTable = $tableNames['model_has_permissions'] ?? 'model_has_permissions';

        if (!Schema::hasTable($permissionsTable)) {
            return;
        }

        $permissionIds = DB::table($permissionsTable)
            ->whereIn('name', array_keys($this->permissionMenus))
            ->where('guard_name', 'web')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (!empty($permissionIds)) {
            if (Schema::hasTable($roleHasPermissionsTable)) {
                DB::table($roleHasPermissionsTable)->whereIn('permission_id', $permissionIds)->delete();
            }
            if (Schema::hasTable($modelHasPermissionsTable)) {
                DB::table($modelHasPermissionsTable)->whereIn('permission_id', $permissionIds)->delete();
            }

            DB::table($permissionsTable)->whereIn('id', $permissionIds)->delete();
        }

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};
