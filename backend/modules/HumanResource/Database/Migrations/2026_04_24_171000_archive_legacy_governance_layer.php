<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->archiveTable('org_role_module_permissions', 'legacy_org_role_module_permissions_archive');
        $this->archiveTable('user_org_roles', 'legacy_user_org_roles_archive');
        $this->archiveTable('system_roles', 'legacy_system_roles_archive');

        if (Schema::hasTable('org_role_module_permissions') && Schema::hasColumn('org_role_module_permissions', 'is_active')) {
            DB::table('org_role_module_permissions')->update(['is_active' => 0]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('org_role_module_permissions') && Schema::hasTable('legacy_org_role_module_permissions_archive')) {
            DB::table('org_role_module_permissions')->delete();
            DB::statement('INSERT INTO org_role_module_permissions SELECT * FROM legacy_org_role_module_permissions_archive');
        }
    }

    private function archiveTable(string $source, string $archive): void
    {
        if (!Schema::hasTable($source) || Schema::hasTable($archive)) {
            return;
        }

        Schema::create($archive, function (Blueprint $table) use ($source): void {
            foreach (Schema::getColumnListing($source) as $column) {
                $table->text($column)->nullable();
            }
            $table->timestamp('archived_at')->nullable();
        });

        $columns = Schema::getColumnListing($source);
        $selectColumns = implode(', ', array_map(fn ($column) => '`' . str_replace('`', '``', $column) . '`', $columns));
        $insertColumns = $selectColumns . ', `archived_at`';
        DB::statement("INSERT INTO {$archive} ({$insertColumns}) SELECT {$selectColumns}, NOW() FROM {$source}");
    }
};
