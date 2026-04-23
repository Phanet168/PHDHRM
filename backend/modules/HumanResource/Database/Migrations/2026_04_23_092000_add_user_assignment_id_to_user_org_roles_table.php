<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_org_roles') || !Schema::hasTable('user_assignments')) {
            return;
        }

        Schema::table('user_org_roles', function (Blueprint $table) {
            if (!Schema::hasColumn('user_org_roles', 'user_assignment_id')) {
                $table->unsignedBigInteger('user_assignment_id')->nullable()->after('user_id');
                $table->index('user_assignment_id', 'idx_uor_user_assignment_id');
                $table->foreign('user_assignment_id', 'fk_uor_user_assignment')
                    ->references('id')
                    ->on('user_assignments')
                    ->nullOnDelete();
            }
        });

        if (Schema::hasColumn('user_org_roles', 'user_assignment_id')) {
            DB::statement("
                UPDATE user_org_roles uor
                JOIN user_assignments ua
                  ON ua.user_id = uor.user_id
                 AND ua.department_id = uor.department_id
                 AND (
                    (uor.system_role_id IS NOT NULL AND ua.responsibility_id = uor.system_role_id)
                    OR (uor.system_role_id IS NULL)
                 )
                SET uor.user_assignment_id = ua.id
                WHERE uor.user_assignment_id IS NULL
            ");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('user_org_roles')) {
            return;
        }

        Schema::table('user_org_roles', function (Blueprint $table) {
            if (Schema::hasColumn('user_org_roles', 'user_assignment_id')) {
                $table->dropForeign('fk_uor_user_assignment');
                $table->dropIndex('idx_uor_user_assignment_id');
                $table->dropColumn('user_assignment_id');
            }
        });
    }
};
