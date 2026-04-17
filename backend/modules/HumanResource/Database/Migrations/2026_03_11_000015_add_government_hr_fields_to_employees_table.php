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
        Schema::create('employee_profile_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('salutation', 40)->nullable();
            $table->date('passport_expiry_date')->nullable();
            $table->date('national_id_expiry_date')->nullable();
            $table->string('birth_place_state', 191)->nullable();
            $table->string('birth_place_city', 191)->nullable();
            $table->string('birth_place_commune', 191)->nullable();
            $table->string('birth_place_village', 191)->nullable();
            $table->string('current_work_skill', 191)->nullable();
            $table->date('current_position_start_date')->nullable();
            $table->string('current_position_document_number', 120)->nullable();
            $table->date('current_position_document_date')->nullable();
            $table->string('current_salary_type', 80)->nullable();
            $table->string('technical_role_type', 80)->nullable();
            $table->string('framework_type', 80)->nullable();
            $table->date('registration_date')->nullable();
            $table->string('professional_registration_no', 120)->nullable();
            $table->string('institution_contact_no', 60)->nullable();
            $table->string('institution_email', 191)->nullable();
            $table->updateCreatedBy();
            $table->timestamps();

            $table->unique('employee_id', 'employee_profile_extras_employee_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_profile_extras');
    }
};
