<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PharmAuditOrphan extends Command
{
    protected $signature = 'pharm:audit-orphan {--clean : Soft-delete orphaned records instead of just listing}';
    protected $description = 'Audit (and optionally clean) orphaned UserOrgRole rows for pharmaceutical facilities';

    public function handle(): int
    {
        // ---- Audit Departments ----
        $this->info('=== Departments (All, incl. soft-deleted) ===');

        $unitTypes = DB::table('org_unit_types')->pluck('name_km', 'id');

        $depts = DB::table('departments as d')
            ->select(
                'd.id', 'd.department_name', 'd.parent_id', 'd.unit_type_id',
                'd.is_active', 'd.deleted_at',
                DB::raw('(SELECT department_name FROM departments WHERE id = d.parent_id LIMIT 1) as parent_name')
            )
            ->orderBy('d.unit_type_id')
            ->orderBy('d.id')
            ->get();

        $totalDepts    = $depts->count();
        $activeDepts   = $depts->whereNull('deleted_at')->count();
        $deletedDepts  = $depts->whereNotNull('deleted_at')->count();

        $this->line("Total dept rows: {$totalDepts}  |  Active: {$activeDepts}  |  Soft-deleted: {$deletedDepts}");
        $this->newLine();

        $deptHeaders = ['id', 'unit_type', 'department_name', 'parent_id', 'parent_name', 'active', 'deleted_at'];
        $deptRows = $depts->map(fn($d) => [
            $d->id,
            $unitTypes[$d->unit_type_id] ?? ("type_id=".$d->unit_type_id),
            $d->department_name,
            $d->parent_id ?? '-',
            $d->parent_name ?? '-',
            $d->is_active ? 'YES' : 'no',
            $d->deleted_at ? substr($d->deleted_at, 0, 10) : '-',
        ])->toArray();
        $this->table($deptHeaders, $deptRows);

        // ---- Audit UserOrgRole orphans ----
        $this->info('=== UserOrgRole — All Rows (incl. soft-deleted) ===');

        $allRows = DB::table('user_org_roles as r')
            ->select('r.id', 'r.user_id', 'r.department_id', 'r.org_role', 'r.scope_type', 'r.is_active', 'r.deleted_at',
                     DB::raw('(SELECT full_name FROM users WHERE id = r.user_id LIMIT 1) as user_name'),
                     DB::raw('(SELECT email FROM users WHERE id = r.user_id LIMIT 1) as user_email'),
                     DB::raw('(SELECT deleted_at FROM users WHERE id = r.user_id LIMIT 1) as user_deleted_at'),
                     DB::raw('(SELECT department_name FROM departments WHERE id = r.department_id LIMIT 1) as dept_name'),
                     DB::raw('(SELECT deleted_at FROM departments WHERE id = r.department_id LIMIT 1) as dept_deleted_at'))
            ->orderByRaw('r.is_active DESC, r.id DESC')
            ->get();

        $total = $allRows->count();
        $softDeletedRoles = $allRows->whereNotNull('deleted_at')->count();
        $activeRoles = $allRows->whereNull('deleted_at')->count();

        $this->line("Total rows (ALL incl. soft-deleted): {$total}");
        $this->line("  Active (deleted_at IS NULL): {$activeRoles}");
        $this->line("  Soft-deleted roles: {$softDeletedRoles}");
        $this->newLine();

        $headers = ['id', 'user_id', 'user_name', 'user_del?', 'dept_id', 'dept_name', 'dept_del?', 'role', 'scope', 'active', 'role_del?'];
        $rows = $allRows->map(fn ($r) => [
            $r->id,
            $r->user_id,
            $r->user_name ?? '(missing)',
            $r->user_deleted_at ? substr($r->user_deleted_at, 0, 10) : '-',
            $r->department_id,
            $r->dept_name ?? '(missing)',
            $r->dept_deleted_at ? substr($r->dept_deleted_at, 0, 10) : '-',
            $r->org_role,
            $r->scope_type,
            $r->is_active ? 'YES' : 'no',
            $r->deleted_at ? substr($r->deleted_at, 0, 10) : '-',
        ])->toArray();
        $this->table($headers, $rows);

        // ---- Clean if requested ----
        if ($this->option('clean')) {
            $softDeletedIds = $allRows->whereNotNull('deleted_at')->pluck('id');
            if ($softDeletedIds->isEmpty()) {
                $this->info('No soft-deleted role rows to clean.');
            } else {
                if ($this->confirm("Hard-delete {$softDeletedIds->count()} soft-deleted UserOrgRole rows permanently?")) {
                    DB::table('user_org_roles')->whereIn('id', $softDeletedIds)->delete();
                    $this->info("Permanently deleted {$softDeletedIds->count()} rows.");
                } else {
                    $this->line('Cancelled.');
                }
            }
        }

        // ---- PharmFacilityStock ----
        $this->newLine();
        $this->info('=== PharmFacilityStock Audit ===');
        $totalStock = DB::table('pharm_facility_stocks')->count();
        $allMedIds = DB::table('pharm_medicines')->whereNull('deleted_at')->pluck('id');
        $allDeptIds = DB::table('departments')->whereNull('deleted_at')->pluck('id');
        $orphanStockMed = DB::table('pharm_facility_stocks')->whereNull('deleted_at')->whereNotIn('medicine_id', $allMedIds)->count();
        $orphanStockDept = DB::table('pharm_facility_stocks')->whereNull('deleted_at')->whereNotIn('department_id', $allDeptIds)->count();
        $this->line("Total stock rows: {$totalStock}");
        $this->line("Orphan stock (medicine deleted): {$orphanStockMed}");
        $this->line("Orphan stock (dept deleted): {$orphanStockDept}");

        return 0;
    }
}
