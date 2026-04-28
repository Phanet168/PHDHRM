<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_responsibility_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('template_key', 80)->unique();
            $table->string('name');
            $table->string('name_km')->nullable();
            $table->string('responsibility_key', 80);
            $table->json('actions_json')->nullable();
            $table->json('conditions_json')->nullable();
            $table->json('reviewer_rules_json')->nullable();
            $table->json('approver_rules_json')->nullable();
            $table->json('commenter_rules_json')->nullable();
            $table->enum('default_scope_type', ['self_only', 'self_unit_only', 'self_and_children', 'all'])
                ->default('self_and_children');
            $table->unsignedInteger('sort_order')->default(100);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order'], 'idx_att_gov_templates_active_sort');
        });

        Schema::create('attendance_user_responsibilities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('template_id');
            $table->enum('scope_type', ['self_only', 'self_unit_only', 'self_and_children', 'all'])
                ->default('self_and_children');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
            $table->foreign('department_id')->references('id')->on('departments')->cascadeOnDelete();
            $table->foreign('template_id', 'fk_att_user_resp_template')
                ->references('id')->on('attendance_responsibility_templates')->cascadeOnDelete();
            $table->index(['user_id', 'is_active'], 'idx_att_user_resp_user_active');
            $table->index(['department_id', 'is_active'], 'idx_att_user_resp_dept_active');
            $table->index(['template_id', 'is_active'], 'idx_att_user_resp_template_active');
        });

        Schema::create('attendance_workflow_policies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('policy_key', 80)->unique();
            $table->string('request_type_key', 80);
            $table->string('name');
            $table->string('name_km')->nullable();
            $table->json('conditions_json')->nullable();
            $table->json('steps_json')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['request_type_key', 'is_active', 'priority'], 'idx_att_workflow_req_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_workflow_policies');
        Schema::dropIfExists('attendance_user_responsibilities');
        Schema::dropIfExists('attendance_responsibility_templates');
    }
};
