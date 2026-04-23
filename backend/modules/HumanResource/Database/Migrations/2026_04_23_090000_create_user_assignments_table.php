<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_assignments')) {
            return;
        }

        Schema::create('user_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('position_id')->nullable();
            $table->unsignedBigInteger('responsibility_id')->nullable();
            $table->enum('scope_type', ['self_only', 'self_unit_only', 'self_and_children', 'all'])
                ->default('self_and_children');
            $table->boolean('is_primary')->default(false);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Database-level guard: one active primary assignment per user.
            $table->unsignedBigInteger('active_primary_user_id')
                ->nullable()
                ->storedAs('IF(is_active = 1 AND is_primary = 1, user_id, NULL)');

            $table->foreign('user_id', 'fk_user_assignments_user')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('department_id', 'fk_user_assignments_department')
                ->references('id')
                ->on('departments')
                ->onDelete('cascade');

            $table->foreign('position_id', 'fk_user_assignments_position')
                ->references('id')
                ->on('positions')
                ->nullOnDelete();

            $table->foreign('responsibility_id', 'fk_user_assignments_responsibility')
                ->references('id')
                ->on('system_roles')
                ->nullOnDelete();

            $table->index(['user_id', 'is_active'], 'idx_user_assignments_user_active');
            $table->index(['department_id', 'is_active'], 'idx_user_assignments_department_active');
            $table->index(['position_id', 'is_active'], 'idx_user_assignments_position_active');
            $table->index(['responsibility_id', 'is_active'], 'idx_user_assignments_responsibility_active');
            $table->index(['scope_type', 'is_active'], 'idx_user_assignments_scope_active');
            $table->index(['effective_from', 'effective_to'], 'idx_user_assignments_effective_dates');
            $table->unique('active_primary_user_id', 'ux_user_assignments_active_primary_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_assignments');
    }
};
