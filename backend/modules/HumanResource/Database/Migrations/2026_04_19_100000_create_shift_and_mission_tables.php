<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('shifts')) {
            Schema::create('shifts', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('code', 30)->nullable()->index();
                $table->string('name');
                $table->time('start_time');
                $table->time('end_time');
                $table->boolean('is_cross_day')->default(false);
                $table->unsignedSmallInteger('grace_late_minutes')->default(0);
                $table->unsignedSmallInteger('grace_early_leave_minutes')->default(0);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('shift_assignments')) {
            Schema::create('shift_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('shift_id');
                $table->date('effective_date');
                $table->date('end_date')->nullable();
                $table->boolean('is_roster_override')->default(false);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('note', 500)->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'effective_date'], 'idx_shift_assign_employee_effective');
                $table->index(['shift_id', 'effective_date'], 'idx_shift_assign_shift_effective');
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('shift_id')->references('id')->on('shifts')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('shift_rosters')) {
            Schema::create('shift_rosters', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('shift_id')->nullable();
                $table->date('roster_date');
                $table->boolean('is_day_off')->default(false);
                $table->boolean('is_holiday')->default(false);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('note', 500)->nullable();
                $table->timestamps();

                $table->unique(['employee_id', 'roster_date'], 'uniq_shift_roster_employee_date');
                $table->index(['roster_date'], 'idx_shift_roster_date');
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('shift_id')->references('id')->on('shifts')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('missions')) {
            Schema::create('missions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('title');
                $table->date('start_date');
                $table->date('end_date');
                $table->string('destination', 255);
                $table->text('purpose')->nullable();
                $table->string('order_attachment_path', 1000)->nullable();
                $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'cancelled'])->default('draft');
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->string('rejected_reason', 1000)->nullable();
                $table->unsignedBigInteger('workflow_instance_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['start_date', 'end_date'], 'idx_missions_date_range');
                $table->index(['status'], 'idx_missions_status');
            });
        }

        if (!Schema::hasTable('mission_assignments')) {
            Schema::create('mission_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mission_id');
                $table->unsignedBigInteger('employee_id');
                $table->enum('status', ['active', 'cancelled'])->default('active');
                $table->string('assignment_note', 500)->nullable();
                $table->timestamps();

                $table->unique(['mission_id', 'employee_id'], 'uniq_mission_employee');
                $table->index(['employee_id', 'status'], 'idx_mission_assign_employee_status');
                $table->foreign('mission_id')->references('id')->on('missions')->cascadeOnDelete();
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_assignments');
        Schema::dropIfExists('missions');
        Schema::dropIfExists('shift_rosters');
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('shifts');
    }
};
