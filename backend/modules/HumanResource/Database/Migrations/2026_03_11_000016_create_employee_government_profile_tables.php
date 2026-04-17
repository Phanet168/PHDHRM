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
        Schema::create('employee_family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('relation_type', 80)->nullable();
            $table->string('occupation', 191)->nullable();
            $table->string('salutation', 40)->nullable();
            $table->string('last_name_km', 191)->nullable();
            $table->string('first_name_km', 191)->nullable();
            $table->string('last_name_latin', 191)->nullable();
            $table->string('first_name_latin', 191)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('nationality', 120)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('birth_place_state', 191)->nullable();
            $table->string('birth_place_city', 191)->nullable();
            $table->string('birth_place_commune', 191)->nullable();
            $table->string('birth_place_village', 191)->nullable();
            $table->string('phone', 60)->nullable();
            $table->boolean('is_deceased')->default(false);
            $table->text('note')->nullable();
            $table->updateCreatedBy();
            $table->timestamps();
        });

        Schema::create('employee_education_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('institution_name', 191)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('degree_level', 191)->nullable();
            $table->text('note')->nullable();
            $table->updateCreatedBy();
            $table->timestamps();
        });

        Schema::create('employee_foreign_languages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('language_name', 120)->nullable();
            $table->string('speaking_level', 10)->nullable();
            $table->string('reading_level', 10)->nullable();
            $table->string('writing_level', 10)->nullable();
            $table->string('institution_name', 191)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('result', 191)->nullable();
            $table->updateCreatedBy();
            $table->timestamps();
        });

        Schema::create('employee_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('account_name', 191)->nullable();
            $table->string('account_number', 120)->nullable();
            $table->string('bank_name', 191)->nullable();
            $table->string('attachment_name', 191)->nullable();
            $table->string('attachment_path')->nullable();
            $table->updateCreatedBy();
            $table->timestamps();
        });

        Schema::create('employee_pay_grade_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('note')->nullable();
            $table->updateCreatedBy();
            $table->timestamps();
        });

        Schema::create('employee_work_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('work_status_name', 191)->nullable();
            $table->date('start_date')->nullable();
            $table->string('document_reference', 191)->nullable();
            $table->date('document_date')->nullable();
            $table->text('note')->nullable();
            $table->updateCreatedBy();
            $table->timestamps();
        });

        Schema::create('employee_incentives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('incentive_date')->nullable();
            $table->string('hierarchy_level', 191)->nullable();
            $table->string('nationality_type', 191)->nullable();
            $table->string('incentive_type', 191)->nullable();
            $table->string('incentive_class', 191)->nullable();
            $table->text('reason')->nullable();
            $table->updateCreatedBy();
            $table->timestamps();
        });

        Schema::create('employee_section_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('section', 50);
            $table->string('title', 191)->nullable();
            $table->string('file_name', 191)->nullable();
            $table->string('file_path')->nullable();
            $table->date('expiry_date')->nullable();
            $table->updateCreatedBy();
            $table->timestamps();

            $table->index(['employee_id', 'section'], 'employee_section_attachments_emp_section_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_section_attachments');
        Schema::dropIfExists('employee_incentives');
        Schema::dropIfExists('employee_work_histories');
        Schema::dropIfExists('employee_pay_grade_histories');
        Schema::dropIfExists('employee_bank_accounts');
        Schema::dropIfExists('employee_foreign_languages');
        Schema::dropIfExists('employee_education_histories');
        Schema::dropIfExists('employee_family_members');
    }
};

