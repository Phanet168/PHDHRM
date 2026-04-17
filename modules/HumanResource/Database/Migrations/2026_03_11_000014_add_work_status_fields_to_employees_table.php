<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->integer('work_status_id')->nullable()->after('is_left');
            $table->string('work_status_name', 191)->nullable()->after('work_status_id');
            $table->string('service_state', 20)->default('active')->after('work_status_name');

            $table->index(['work_status_id'], 'employees_work_status_id_idx');
            $table->index(['service_state'], 'employees_service_state_idx');
        });

        DB::table('employees')
            ->where('is_active', 0)
            ->update(['service_state' => 'inactive']);

        DB::table('employees')
            ->where('is_active', 1)
            ->update(['service_state' => 'active']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('employees_work_status_id_idx');
            $table->dropIndex('employees_service_state_idx');
            $table->dropColumn(['work_status_id', 'work_status_name', 'service_state']);
        });
    }
};

