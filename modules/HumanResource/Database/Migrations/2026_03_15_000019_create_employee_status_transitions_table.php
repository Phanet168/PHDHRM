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
        Schema::create('employee_status_transitions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            $table->string('transition_type', 60)->default('status_change');
            $table->string('transition_source', 80)->nullable();

            $table->string('from_work_status_name', 191)->nullable();
            $table->string('to_work_status_name', 191);
            $table->string('from_service_state', 20)->nullable();
            $table->string('to_service_state', 20);

            $table->boolean('from_is_active')->default(true);
            $table->boolean('to_is_active')->default(true);
            $table->boolean('from_is_left')->default(false);
            $table->boolean('to_is_left')->default(false);

            $table->date('effective_date')->nullable();
            $table->date('document_date')->nullable();
            $table->string('document_reference', 191)->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();

            $table->updateCreatedBy();
            $table->timestamps();

            $table->index(['employee_id', 'effective_date'], 'employee_status_transitions_emp_effective_idx');
            $table->index(['employee_id', 'created_at'], 'employee_status_transitions_emp_created_idx');
            $table->index('transition_type', 'employee_status_transitions_type_idx');
            $table->index('to_service_state', 'employee_status_transitions_to_state_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_status_transitions');
    }
};
