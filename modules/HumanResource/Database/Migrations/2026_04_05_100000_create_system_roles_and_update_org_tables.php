<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 1. Create system_roles table ───
        Schema::create('system_roles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
            $table->string('name_km', 120)->nullable();
            $table->unsignedTinyInteger('level')->default(0);
            $table->boolean('can_approve')->default(false);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        // ─── 2. Seed default system roles ───
        $now = now();
        $roles = [
            ['code' => 'head',        'name' => 'Head / Director',           'name_km' => "\u{1794}\u{17D2}\u{179A}\u{1792}\u{17B6}\u{1793}",                    'level' => 1, 'can_approve' => true,  'is_system' => true, 'sort_order' => 1],
            ['code' => 'deputy_head', 'name' => 'Deputy Head',               'name_km' => "\u{17A2}\u{1793}\u{17BB}\u{1794}\u{17D2}\u{179A}\u{1792}\u{17B6}\u{1793}",              'level' => 2, 'can_approve' => true,  'is_system' => true, 'sort_order' => 2],
            ['code' => 'manager',     'name' => 'Manager / Office Chief',    'name_km' => "\u{1794}\u{17D2}\u{179A}\u{1792}\u{17B6}\u{1793}\u{1780}\u{17B6}\u{179A}\u{17B7}\u{1799}\u{17B6}\u{179B}\u{17D0}\u{1799}",  'level' => 3, 'can_approve' => true,  'is_system' => true, 'sort_order' => 3],
            ['code' => 'reviewer',    'name' => 'Reviewer',                  'name_km' => "\u{17A2}\u{17D2}\u{1793}\u{1780}\u{1796}\u{17B7}\u{1793}\u{17B7}\u{178F}\u{17D2}\u{1799}",              'level' => 4, 'can_approve' => false, 'is_system' => true, 'sort_order' => 4],
            ['code' => 'staff',       'name' => 'Staff',                     'name_km' => "\u{1794}\u{17BB}\u{1782}\u{17D2}\u{1782}\u{179B}\u{17B7}\u{1780}",                 'level' => 5, 'can_approve' => false, 'is_system' => true, 'sort_order' => 5],
            ['code' => 'viewer',      'name' => 'Viewer (Read-only)',        'name_km' => "\u{17A2}\u{17D2}\u{1793}\u{1780}\u{1798}\u{17BE}\u{179B}",                  'level' => 6, 'can_approve' => false, 'is_system' => true, 'sort_order' => 6],
        ];

        foreach ($roles as $role) {
            DB::table('system_roles')->insert(array_merge($role, [
                'uuid'       => (string) Str::uuid(),
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // ─── 3. Add system_role_id to user_org_roles ───
        if (Schema::hasTable('user_org_roles')) {
            Schema::table('user_org_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('system_role_id')->nullable()->after('org_role');
                $table->foreign('system_role_id')
                    ->references('id')->on('system_roles')
                    ->onDelete('restrict');
                $table->index(['system_role_id', 'is_active'], 'idx_uor_system_role');
            });

            // Migrate existing data
            DB::statement("
                UPDATE user_org_roles uor
                JOIN system_roles sr ON sr.code = uor.org_role
                SET uor.system_role_id = sr.id
                WHERE uor.system_role_id IS NULL
            ");

            // Update scope_type: 'self' → 'self_only'
            DB::table('user_org_roles')
                ->where('scope_type', 'self')
                ->update(['scope_type' => 'self_only']);
        }

        // ─── 4. Add system_role_id to org_role_module_permissions ───
        if (Schema::hasTable('org_role_module_permissions')) {
            Schema::table('org_role_module_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('system_role_id')->nullable()->after('org_role');
                $table->foreign('system_role_id')
                    ->references('id')->on('system_roles')
                    ->onDelete('restrict');
            });

            DB::statement("
                UPDATE org_role_module_permissions ormp
                JOIN system_roles sr ON sr.code = ormp.org_role
                SET ormp.system_role_id = sr.id
                WHERE ormp.system_role_id IS NULL
            ");
        }

        // ─── 5. Add system_role_id to workflow_definition_steps ───
        if (Schema::hasTable('workflow_definition_steps')) {
            Schema::table('workflow_definition_steps', function (Blueprint $table) {
                $table->unsignedBigInteger('system_role_id')->nullable()->after('org_role');
                $table->foreign('system_role_id')
                    ->references('id')->on('system_roles')
                    ->onDelete('restrict');
            });

            DB::statement("
                UPDATE workflow_definition_steps wds
                JOIN system_roles sr ON sr.code = wds.org_role
                SET wds.system_role_id = sr.id
                WHERE wds.system_role_id IS NULL
            ");

            // Update scope_type: 'self' → 'self_only'
            DB::table('workflow_definition_steps')
                ->where('scope_type', 'self')
                ->update(['scope_type' => 'self_only']);
        }
    }

    public function down(): void
    {
        // Revert scope_type back
        if (Schema::hasTable('user_org_roles')) {
            DB::table('user_org_roles')
                ->where('scope_type', 'self_only')
                ->update(['scope_type' => 'self']);
        }
        if (Schema::hasTable('workflow_definition_steps')) {
            DB::table('workflow_definition_steps')
                ->where('scope_type', 'self_only')
                ->update(['scope_type' => 'self']);
        }

        // Drop FK columns
        if (Schema::hasTable('workflow_definition_steps') && Schema::hasColumn('workflow_definition_steps', 'system_role_id')) {
            Schema::table('workflow_definition_steps', function (Blueprint $table) {
                $table->dropForeign(['system_role_id']);
                $table->dropColumn('system_role_id');
            });
        }

        if (Schema::hasTable('org_role_module_permissions') && Schema::hasColumn('org_role_module_permissions', 'system_role_id')) {
            Schema::table('org_role_module_permissions', function (Blueprint $table) {
                $table->dropForeign(['system_role_id']);
                $table->dropColumn('system_role_id');
            });
        }

        if (Schema::hasTable('user_org_roles') && Schema::hasColumn('user_org_roles', 'system_role_id')) {
            Schema::table('user_org_roles', function (Blueprint $table) {
                $table->dropForeign(['system_role_id']);
                $table->dropIndex('idx_uor_system_role');
                $table->dropColumn('system_role_id');
            });
        }

        Schema::dropIfExists('system_roles');
    }
};
