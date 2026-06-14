<?php

namespace Modules\Planning\Services;

use Illuminate\Support\Facades\Schema;

class PlanningModuleStateService
{
    public function isInstalled(): bool
    {
        $requiredTables = [
            'org_units',
            'chapters',
            'accounts',
            'sub_accounts',
            'programs',
            'sub_programs',
            'activity_clusters',
            'chart_of_accounts',
            'funding_sources',
            'indicators',
            'plans',
            'plan_items',
            'plan_item_schedules',
            'plan_item_costs',
            'plan_item_indicators',
            'plan_personnel_lines',
            'plan_revenue_lines',
            'plan_approvals',
            'plan_comments',
            'plan_attachments',
            'unit_cluster_permissions',
            'cluster_chart_account_rules',
        ];

        foreach ($requiredTables as $table) {
            if (!Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }
}
