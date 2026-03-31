<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('attendances', 'workplace_id')) {
                $table->unsignedBigInteger('workplace_id')->nullable()->after('employee_id');
                $table->index('workplace_id', 'attendances_workplace_id_idx');
            }

            if (!Schema::hasColumn('attendances', 'attendance_source')) {
                $table->string('attendance_source', 50)->default('manual')->after('machine_state');
                $table->index('attendance_source', 'attendances_source_idx');
            }

            if (!Schema::hasColumn('attendances', 'source_reference')) {
                $table->string('source_reference')->nullable()->after('attendance_source');
            }

            if (!Schema::hasColumn('attendances', 'scan_latitude')) {
                $table->decimal('scan_latitude', 11, 7)->nullable()->after('source_reference');
            }

            if (!Schema::hasColumn('attendances', 'scan_longitude')) {
                $table->decimal('scan_longitude', 11, 7)->nullable()->after('scan_latitude');
            }

            if (!Schema::hasColumn('attendances', 'exception_flag')) {
                $table->boolean('exception_flag')->default(false)->after('time');
                $table->index('exception_flag', 'attendances_exception_flag_idx');
            }

            if (!Schema::hasColumn('attendances', 'exception_reason')) {
                $table->string('exception_reason')->nullable()->after('exception_flag');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (Schema::hasColumn('attendances', 'exception_reason')) {
                $table->dropColumn('exception_reason');
            }
            if (Schema::hasColumn('attendances', 'exception_flag')) {
                $table->dropIndex('attendances_exception_flag_idx');
                $table->dropColumn('exception_flag');
            }
            if (Schema::hasColumn('attendances', 'scan_longitude')) {
                $table->dropColumn('scan_longitude');
            }
            if (Schema::hasColumn('attendances', 'scan_latitude')) {
                $table->dropColumn('scan_latitude');
            }
            if (Schema::hasColumn('attendances', 'source_reference')) {
                $table->dropColumn('source_reference');
            }
            if (Schema::hasColumn('attendances', 'attendance_source')) {
                $table->dropIndex('attendances_source_idx');
                $table->dropColumn('attendance_source');
            }
            if (Schema::hasColumn('attendances', 'workplace_id')) {
                $table->dropIndex('attendances_workplace_id_idx');
                $table->dropColumn('workplace_id');
            }
        });
    }
};

