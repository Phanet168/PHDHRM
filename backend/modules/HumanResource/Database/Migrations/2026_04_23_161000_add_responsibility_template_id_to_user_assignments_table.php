<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_assignments')) {
            return;
        }

        Schema::table('user_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('user_assignments', 'responsibility_template_id')) {
                $table->unsignedBigInteger('responsibility_template_id')
                    ->nullable()
                    ->after('position_id');
            }
        });

        Schema::table('user_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('user_assignments', 'responsibility_template_id')) {
                $table->index(
                    ['responsibility_template_id', 'is_active'],
                    'idx_user_assignments_template_active'
                );
                $table->foreign('responsibility_template_id', 'fk_user_assignments_template')
                    ->references('id')
                    ->on('responsibility_templates')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('user_assignments') || !Schema::hasColumn('user_assignments', 'responsibility_template_id')) {
            return;
        }

        Schema::table('user_assignments', function (Blueprint $table) {
            $table->dropForeign('fk_user_assignments_template');
            $table->dropIndex('idx_user_assignments_template_active');
            $table->dropColumn('responsibility_template_id');
        });
    }
};

