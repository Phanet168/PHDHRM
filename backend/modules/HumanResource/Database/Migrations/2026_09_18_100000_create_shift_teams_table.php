<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance Management Phase C: a reusable, pre-defined duty-shift team
 * (e.g. "Team A") an admin sets up once per facility, so a duty roster can
 * be assigned to the whole team in one action instead of one employee at a
 * time. Mirrors the existing shifts/shift_assignments table shapes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shift_teams')) {
            Schema::create('shift_teams', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('department_id');
                $table->string('name');
                $table->string('code', 30)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('department_id')->references('id')->on('departments')->cascadeOnDelete();
                $table->index(['department_id', 'is_active']);
            });
        }

        if (!Schema::hasTable('shift_team_members')) {
            Schema::create('shift_team_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('shift_team_id');
                $table->unsignedBigInteger('employee_id');
                $table->boolean('is_active')->default(true);
                $table->date('joined_at')->nullable();
                $table->date('left_at')->nullable();
                $table->timestamps();

                $table->foreign('shift_team_id')->references('id')->on('shift_teams')->cascadeOnDelete();
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->unique(['shift_team_id', 'employee_id'], 'uniq_shift_team_member');
                $table->index(['employee_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_team_members');
        Schema::dropIfExists('shift_teams');
    }
};
