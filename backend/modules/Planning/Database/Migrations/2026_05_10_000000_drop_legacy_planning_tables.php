<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            $tables = [
                'approval_histories',
                'mission_items',
                'office_supply_items',
                'micro_plan_items',
                'micro_plans',
                'bsp_macro_items',
                'bsp_plans',
                'plan_items',
                'plan_progresses',
                'plan_budgets',
                'plan_activities',
                'plans',
                'plan_approval_workflows',
                'plan_progress_updates',
                'plan_budget_breakdowns',
                'aop_sheet_snapshots',
                'aop_financial_lines',
                'aop_activity_cost_items',
                'aop_plan_activities',
                'aop_plan_indicators',
                'aop_plan_structures',
                'aop_plans',
                'department_plan_budget_items',
                'department_plan_activities',
                'department_plan_activity_sections',
                'department_plans',
            ];

            foreach ($tables as $table) {
                Schema::dropIfExists($table);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        if (Schema::hasTable('permissions')) {
            $legacyPermissions = [
                'planning.manage',
                'planning.view.own',
                'planning.view.department',
                'planning.create',
                'planning.update.own',
                'planning.delete.own',
                'planning.submit.own',
                'planning.review',
                'planning.approve',
                'planning.reject',
            ];

            $permissionIds = DB::table('permissions')
                ->whereIn('name', $legacyPermissions)
                ->pluck('id');

            if ($permissionIds->isNotEmpty()) {
                if (Schema::hasTable('role_has_permissions')) {
                    DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
                }

                if (Schema::hasTable('model_has_permissions')) {
                    DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
                }

                DB::table('permissions')->whereIn('id', $permissionIds)->delete();
            }
        }
    }

    public function down(): void
    {
        // Legacy planning tables are intentionally not recreated.
    }
};
