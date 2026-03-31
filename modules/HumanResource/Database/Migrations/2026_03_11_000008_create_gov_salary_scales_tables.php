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
        Schema::create('gov_salary_scales', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name_en', 120);
            $table->string('name_km', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->updateCreatedBy();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('gov_salary_scale_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gov_salary_scale_id')
                ->constrained('gov_salary_scales')
                ->cascadeOnDelete();
            $table->foreignId('professional_skill_id')
                ->constrained('professional_skills')
                ->cascadeOnDelete();
            $table->decimal('value', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(
                ['gov_salary_scale_id', 'professional_skill_id'],
                'gov_salary_scale_values_scale_skill_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gov_salary_scale_values');
        Schema::dropIfExists('gov_salary_scales');
    }
};
