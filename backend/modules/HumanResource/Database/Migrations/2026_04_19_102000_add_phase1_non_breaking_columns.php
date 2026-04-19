<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                if (!Schema::hasColumn('attendances', 'attendance_status')) {
                    $table->string('attendance_status', 32)->nullable()->index('idx_attendance_status');
                }
                if (!Schema::hasColumn('attendances', 'shift_id')) {
                    $table->unsignedBigInteger('shift_id')->nullable()->index('idx_attendance_shift_id');
                }
                if (!Schema::hasColumn('attendances', 'leave_id')) {
                    $table->unsignedBigInteger('leave_id')->nullable()->index('idx_attendance_leave_id');
                }
                if (!Schema::hasColumn('attendances', 'mission_id')) {
                    $table->unsignedBigInteger('mission_id')->nullable()->index('idx_attendance_mission_id');
                }
                if (!Schema::hasColumn('attendances', 'is_manual_adjustment')) {
                    $table->boolean('is_manual_adjustment')->default(false);
                }
                if (!Schema::hasColumn('attendances', 'adjusted_by_id')) {
                    $table->unsignedBigInteger('adjusted_by_id')->nullable();
                }
                if (!Schema::hasColumn('attendances', 'adjustment_reason')) {
                    $table->text('adjustment_reason')->nullable();
                }
            });

            Schema::table('attendances', function (Blueprint $table) {
                if (Schema::hasColumn('attendances', 'shift_id')) {
                    $table->foreign('shift_id', 'fk_attendance_shift_phase1')->references('id')->on('shifts')->nullOnDelete();
                }
                if (Schema::hasColumn('attendances', 'leave_id')) {
                    $table->foreign('leave_id', 'fk_attendance_leave_phase1')->references('id')->on('apply_leaves')->nullOnDelete();
                }
                if (Schema::hasColumn('attendances', 'mission_id')) {
                    $table->foreign('mission_id', 'fk_attendance_mission_phase1')->references('id')->on('missions')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('apply_leaves')) {
            Schema::table('apply_leaves', function (Blueprint $table) {
                if (!Schema::hasColumn('apply_leaves', 'leave_category')) {
                    $table->string('leave_category', 32)->default('leave');
                }
                if (!Schema::hasColumn('apply_leaves', 'mission_id')) {
                    $table->unsignedBigInteger('mission_id')->nullable()->index('idx_apply_leaves_mission_id');
                }
            });

            Schema::table('apply_leaves', function (Blueprint $table) {
                if (Schema::hasColumn('apply_leaves', 'mission_id')) {
                    $table->foreign('mission_id', 'fk_apply_leaves_mission_phase1')->references('id')->on('missions')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('departments')) {
            Schema::table('departments', function (Blueprint $table) {
                if (!Schema::hasColumn('departments', 'geofence_latitude')) {
                    $table->decimal('geofence_latitude', 11, 7)->nullable();
                }
                if (!Schema::hasColumn('departments', 'geofence_longitude')) {
                    $table->decimal('geofence_longitude', 11, 7)->nullable();
                }
                if (!Schema::hasColumn('departments', 'geofence_radius_meters')) {
                    $table->unsignedInteger('geofence_radius_meters')->default(500);
                }
            });
        }

        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (!Schema::hasColumn('employees', 'default_shift_id')) {
                    $table->unsignedBigInteger('default_shift_id')->nullable()->index('idx_employees_default_shift_id');
                }
            });

            Schema::table('employees', function (Blueprint $table) {
                if (Schema::hasColumn('employees', 'default_shift_id')) {
                    $table->foreign('default_shift_id', 'fk_employees_default_shift_phase1')->references('id')->on('shifts')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (Schema::hasColumn('employees', 'default_shift_id')) {
                    $table->dropForeign('fk_employees_default_shift_phase1');
                    $table->dropColumn('default_shift_id');
                }
            });
        }

        if (Schema::hasTable('departments')) {
            Schema::table('departments', function (Blueprint $table) {
                if (Schema::hasColumn('departments', 'geofence_radius_meters')) {
                    $table->dropColumn('geofence_radius_meters');
                }
                if (Schema::hasColumn('departments', 'geofence_longitude')) {
                    $table->dropColumn('geofence_longitude');
                }
                if (Schema::hasColumn('departments', 'geofence_latitude')) {
                    $table->dropColumn('geofence_latitude');
                }
            });
        }

        if (Schema::hasTable('apply_leaves')) {
            Schema::table('apply_leaves', function (Blueprint $table) {
                if (Schema::hasColumn('apply_leaves', 'mission_id')) {
                    $table->dropForeign('fk_apply_leaves_mission_phase1');
                    $table->dropColumn('mission_id');
                }
                if (Schema::hasColumn('apply_leaves', 'leave_category')) {
                    $table->dropColumn('leave_category');
                }
            });
        }

        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                if (Schema::hasColumn('attendances', 'mission_id')) {
                    $table->dropForeign('fk_attendance_mission_phase1');
                    $table->dropColumn('mission_id');
                }
                if (Schema::hasColumn('attendances', 'leave_id')) {
                    $table->dropForeign('fk_attendance_leave_phase1');
                    $table->dropColumn('leave_id');
                }
                if (Schema::hasColumn('attendances', 'shift_id')) {
                    $table->dropForeign('fk_attendance_shift_phase1');
                    $table->dropColumn('shift_id');
                }
                if (Schema::hasColumn('attendances', 'adjustment_reason')) {
                    $table->dropColumn('adjustment_reason');
                }
                if (Schema::hasColumn('attendances', 'adjusted_by_id')) {
                    $table->dropColumn('adjusted_by_id');
                }
                if (Schema::hasColumn('attendances', 'is_manual_adjustment')) {
                    $table->dropColumn('is_manual_adjustment');
                }
                if (Schema::hasColumn('attendances', 'attendance_status')) {
                    $table->dropColumn('attendance_status');
                }
            });
        }
    }
};
