<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditRoleGovernance extends Command
{
    protected $signature = 'rbac:audit-role-governance {--json : Output as JSON}';

    protected $description = 'Audit role-governance consistency (Spatie roles, org roles, system roles, and workflow mappings).';

    public function handle(): int
    {
        $metrics = $this->collectMetrics();
        $duplicateNames = $this->collectDuplicateUserNames();
        $correspondencePermissionRoles = $this->collectCorrespondencePermissionRoles();

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'metrics' => $metrics,
                'duplicate_user_names' => $duplicateNames,
                'roles_with_correspondence_permissions' => $correspondencePermissionRoles,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info('RBAC Governance Audit');
        $this->newLine();

        $this->table(
            ['Check', 'Value'],
            collect($metrics)
                ->map(fn ($value, $key) => [
                    str_replace('_', ' ', (string) $key),
                    (string) $value,
                ])
                ->values()
                ->all()
        );

        $this->newLine();
        $this->info('Duplicate user names (top 20)');
        if (empty($duplicateNames)) {
            $this->line('- none');
        } else {
            $this->table(['full_name', 'count'], $duplicateNames);
        }

        $this->newLine();
        $this->info('Roles with correspondence permissions');
        if (empty($correspondencePermissionRoles)) {
            $this->line('- none');
        } else {
            $this->table(['role', 'permission_count'], $correspondencePermissionRoles);
        }

        $this->newLine();
        $this->comment('Tip: run `php artisan rbac:audit-role-governance --json` for machine-readable output.');

        return self::SUCCESS;
    }

    protected function collectMetrics(): array
    {
        $tableNames = config('permission.table_names', []);
        $rolesTable = $tableNames['roles'] ?? 'roles';
        $modelHasRolesTable = $tableNames['model_has_roles'] ?? 'model_has_roles';

        $usersTotal = Schema::hasTable('users')
            ? (int) DB::table('users')->whereNull('deleted_at')->count()
            : 0;

        $usersWithoutSpatieRole = 0;
        if (Schema::hasTable('users') && Schema::hasTable($modelHasRolesTable)) {
            $usersWithoutSpatieRole = (int) DB::table('users')
                ->leftJoin($modelHasRolesTable . ' as mhr', function ($join) {
                    $join->on('mhr.model_id', '=', 'users.id')
                        ->where('mhr.model_type', '=', 'App\\Models\\User');
                })
                ->whereNull('users.deleted_at')
                ->whereNull('mhr.role_id')
                ->count();
        }

        $userOrgRolesTotal = Schema::hasTable('user_org_roles')
            ? (int) DB::table('user_org_roles')->whereNull('deleted_at')->count()
            : 0;
        $userOrgRolesMissingSystemRole = (Schema::hasTable('user_org_roles') && Schema::hasColumn('user_org_roles', 'system_role_id'))
            ? (int) DB::table('user_org_roles')
                ->whereNull('deleted_at')
                ->whereNull('system_role_id')
                ->count()
            : 0;

        $matrixTotal = Schema::hasTable('org_role_module_permissions')
            ? (int) DB::table('org_role_module_permissions')->count()
            : 0;
        $matrixMissingSystemRole = (Schema::hasTable('org_role_module_permissions') && Schema::hasColumn('org_role_module_permissions', 'system_role_id'))
            ? (int) DB::table('org_role_module_permissions')->whereNull('system_role_id')->count()
            : 0;

        $workflowStepTotal = Schema::hasTable('workflow_definition_steps')
            ? (int) DB::table('workflow_definition_steps')->whereNull('deleted_at')->count()
            : 0;
        $workflowStepMissingSystemRole = (Schema::hasTable('workflow_definition_steps') && Schema::hasColumn('workflow_definition_steps', 'system_role_id'))
            ? (int) DB::table('workflow_definition_steps')
                ->whereNull('deleted_at')
                ->whereNull('system_role_id')
                ->count()
            : 0;

        $systemRolesTotal = Schema::hasTable('system_roles')
            ? (int) DB::table('system_roles')->count()
            : 0;
        $systemRolesUnused = 0;
        if (Schema::hasTable('system_roles')) {
            $systemRolesUnused = (int) DB::table('system_roles as sr')
                ->leftJoin('user_org_roles as uor', function ($join) {
                    $join->on('uor.system_role_id', '=', 'sr.id')
                        ->whereNull('uor.deleted_at');
                })
                ->leftJoin('org_role_module_permissions as ormp', 'ormp.system_role_id', '=', 'sr.id')
                ->leftJoin('workflow_definition_steps as wds', function ($join) {
                    $join->on('wds.system_role_id', '=', 'sr.id')
                        ->whereNull('wds.deleted_at');
                })
                ->whereNull('uor.id')
                ->whereNull('ormp.id')
                ->whereNull('wds.id')
                ->count();
        }

        return [
            'users_total' => $usersTotal,
            'users_without_spatie_role' => $usersWithoutSpatieRole,
            'user_org_roles_total' => $userOrgRolesTotal,
            'user_org_roles_missing_system_role' => $userOrgRolesMissingSystemRole,
            'matrix_rows_total' => $matrixTotal,
            'matrix_rows_missing_system_role' => $matrixMissingSystemRole,
            'workflow_steps_total' => $workflowStepTotal,
            'workflow_steps_missing_system_role' => $workflowStepMissingSystemRole,
            'system_roles_total' => $systemRolesTotal,
            'system_roles_unused' => $systemRolesUnused,
        ];
    }

    protected function collectDuplicateUserNames(): array
    {
        if (!Schema::hasTable('users')) {
            return [];
        }

        return DB::table('users')
            ->select('full_name', DB::raw('COUNT(*) as total'))
            ->whereNull('deleted_at')
            ->whereNotNull('full_name')
            ->whereRaw('TRIM(full_name) <> ""')
            ->groupBy('full_name')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('total')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'full_name' => (string) $row->full_name,
                'count' => (int) $row->total,
            ])
            ->all();
    }

    protected function collectCorrespondencePermissionRoles(): array
    {
        $tableNames = config('permission.table_names', []);
        $rolesTable = $tableNames['roles'] ?? 'roles';
        $permissionsTable = $tableNames['permissions'] ?? 'permissions';
        $roleHasPermissionsTable = $tableNames['role_has_permissions'] ?? 'role_has_permissions';

        if (!Schema::hasTable($rolesTable) || !Schema::hasTable($permissionsTable) || !Schema::hasTable($roleHasPermissionsTable)) {
            return [];
        }

        return DB::table($roleHasPermissionsTable . ' as rp')
            ->join($rolesTable . ' as r', 'r.id', '=', 'rp.role_id')
            ->join($permissionsTable . ' as p', 'p.id', '=', 'rp.permission_id')
            ->where('p.name', 'like', '%correspondence%')
            ->groupBy('r.name')
            ->orderBy('r.name')
            ->select('r.name', DB::raw('COUNT(*) as total'))
            ->get()
            ->map(fn ($row) => [
                'role' => (string) $row->name,
                'permission_count' => (int) $row->total,
            ])
            ->all();
    }
}

