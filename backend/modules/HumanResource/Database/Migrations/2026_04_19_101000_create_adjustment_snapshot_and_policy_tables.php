<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('attendance_adjustments')) {
            Schema::create('attendance_adjustments', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('attendance_id')->nullable();
                $table->dateTime('old_time')->nullable();
                $table->dateTime('new_time')->nullable();
                $table->unsignedTinyInteger('old_machine_state')->nullable();
                $table->unsignedTinyInteger('new_machine_state')->nullable();
                $table->text('reason');
                $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'cancelled'])->default('draft');
                $table->unsignedBigInteger('requested_by');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->string('rejected_reason', 1000)->nullable();
                $table->unsignedBigInteger('workflow_instance_id')->nullable();
                $table->json('audit_meta')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'status'], 'idx_adjustments_employee_status');
                $table->index(['attendance_id'], 'idx_adjustments_attendance');
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('attendance_id')->references('id')->on('attendances')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('attendance_daily_snapshots')) {
            Schema::create('attendance_daily_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->date('snapshot_date');
                $table->unsignedBigInteger('shift_id')->nullable();
                $table->enum('attendance_status', [
                    'Present',
                    'Late',
                    'Early Leave',
                    'Absent',
                    'Incomplete',
                    'On Leave',
                    'On Mission',
                    'Holiday',
                    'Day Off',
                ])->default('Absent');
                $table->dateTime('in_time')->nullable();
                $table->dateTime('out_time')->nullable();
                $table->unsignedInteger('worked_minutes')->nullable();
                $table->unsignedInteger('late_minutes')->nullable();
                $table->unsignedInteger('early_leave_minutes')->nullable();
                $table->unsignedBigInteger('leave_id')->nullable();
                $table->unsignedBigInteger('mission_id')->nullable();
                $table->boolean('is_holiday')->default(false);
                $table->boolean('is_day_off')->default(false);
                $table->json('policy_payload')->nullable();
                $table->dateTime('computed_at')->nullable();
                $table->timestamps();

                $table->unique(['employee_id', 'snapshot_date'], 'uniq_snapshot_employee_date');
                $table->index(['snapshot_date', 'attendance_status'], 'idx_snapshot_date_status');
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('shift_id')->references('id')->on('shifts')->nullOnDelete();
                $table->foreign('leave_id')->references('id')->on('apply_leaves')->nullOnDelete();
                $table->foreign('mission_id')->references('id')->on('missions')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('leave_entitlements')) {
            Schema::create('leave_entitlements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('leave_type_id');
                $table->unsignedBigInteger('academic_year_id')->nullable();
                $table->decimal('entitled_days', 8, 2)->default(0);
                $table->decimal('used_days', 8, 2)->default(0);
                $table->decimal('remaining_days', 8, 2)->default(0);
                $table->dateTime('last_calculated_at')->nullable();
                $table->timestamps();

                $table->unique(['employee_id', 'leave_type_id', 'academic_year_id'], 'uniq_leave_entitlement_scope');
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('leave_type_id')->references('id')->on('leave_types')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('attendance_status_rules')) {
            Schema::create('attendance_status_rules', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('rule_key', 100)->unique();
                $table->string('rule_value', 255);
                $table->string('description', 500)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_status_rules');
        Schema::dropIfExists('leave_entitlements');
        Schema::dropIfExists('attendance_daily_snapshots');
        Schema::dropIfExists('attendance_adjustments');
    }
};
