<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apply_leaves', function (Blueprint $table) {
            if (!Schema::hasColumn('apply_leaves', 'handover_employee_id')) {
                $table->unsignedBigInteger('handover_employee_id')->nullable()->after('employee_id');
                $table->index('handover_employee_id', 'apply_leaves_handover_employee_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('apply_leaves', function (Blueprint $table) {
            if (Schema::hasColumn('apply_leaves', 'handover_employee_id')) {
                $table->dropIndex('apply_leaves_handover_employee_idx');
                $table->dropColumn('handover_employee_id');
            }
        });
    }
};
