<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_work_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('sector_category', 191)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('position_title', 191)->nullable();
            $table->string('institution_name', 191)->nullable();
            $table->text('note')->nullable();
            $table->updateCreatedBy();
            $table->timestamps();

            $table->index(['employee_id', 'sector_category'], 'emp_work_exp_emp_sector_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_work_experiences');
    }
};
