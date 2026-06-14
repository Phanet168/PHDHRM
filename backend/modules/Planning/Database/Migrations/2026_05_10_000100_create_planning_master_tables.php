<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_units', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('source_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('name_km')->nullable();
            $table->enum('unit_type', [
                'provincial_health_department',
                'phd_office',
                'operational_district',
                'od_office',
                'provincial_hospital',
                'referral_hospital',
                'health_center',
                'other',
            ])->default('other');
            $table->unsignedSmallInteger('level')->default(1);
            $table->string('hierarchy_path')->nullable();
            $table->string('org_path_code')->nullable();
            $table->string('manager_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'unit_type'], 'idx_org_units_parent_type');
            $table->index(['source_department_id'], 'idx_org_units_source_department');
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('name_km')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sub_programs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('name_km')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['program_id', 'is_active'], 'idx_sub_programs_program_active');
        });

        Schema::create('activity_clusters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sub_program_id')->constrained('sub_programs')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('name_km')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['sub_program_id', 'is_active'], 'idx_activity_clusters_sub_program_active');
        });

        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->string('chapter_code', 20);
            $table->string('chapter_name');
            $table->string('account_code', 20);
            $table->string('account_name');
            $table->string('subaccount_code', 20);
            $table->string('subaccount_name');
            $table->string('expense_type', 100)->nullable();
            $table->string('name')->nullable();
            $table->string('name_km')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['chapter_code', 'account_code', 'subaccount_code'], 'idx_coa_budget_codes');
        });

        Schema::create('funding_sources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('name_km')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('indicators', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('name_km')->nullable();
            $table->string('unit_of_measure', 100)->nullable();
            $table->enum('value_type', ['number', 'percentage', 'currency', 'text'])->default('number');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicators');
        Schema::dropIfExists('funding_sources');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('activity_clusters');
        Schema::dropIfExists('sub_programs');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('org_units');
    }
};
