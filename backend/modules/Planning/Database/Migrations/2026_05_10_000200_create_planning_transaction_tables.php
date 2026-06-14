<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('org_unit_id')->constrained('org_units')->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('programs')->restrictOnDelete();
            $table->foreignId('sub_program_id')->constrained('sub_programs')->restrictOnDelete();
            $table->foreignId('activity_cluster_id')->constrained('activity_clusters')->restrictOnDelete();
            $table->enum('plan_type', [
                'annual',
                'micro',
                'bsp_3_year',
                'budget',
                'office_supplies',
                'mission_cost',
            ]);
            $table->string('title');
            $table->string('reference_no')->nullable();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('start_year')->nullable()->comment('Used by BSP 3-year plans');
            $table->unsignedSmallInteger('end_year')->nullable()->comment('Used by BSP 3-year plans');
            $table->enum('period_type', ['annual', 'semi_annual', 'quarterly', 'monthly'])->default('annual');
            $table->unsignedTinyInteger('period_no')->nullable();
            $table->string('workflow_status', 50)->default('draft');
            $table->boolean('is_locked')->default(false);
            $table->text('objective')->nullable();
            $table->text('summary')->nullable();
            $table->text('background')->nullable();
            $table->text('assumptions')->nullable();
            $table->decimal('total_estimated_cost', 16, 2)->default(0);
            $table->decimal('total_personnel_cost', 16, 2)->default(0);
            $table->decimal('total_revenue_amount', 16, 2)->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('consolidated_at')->nullable();
            $table->unsignedBigInteger('consolidated_by')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['org_unit_id', 'year', 'period_type', 'period_no', 'plan_type', 'program_id', 'sub_program_id', 'activity_cluster_id'],
                'uq_plans_scope'
            );
            $table->index(['org_unit_id', 'year', 'plan_type'], 'idx_plans_unit_year_type');
            $table->index(['workflow_status', 'plan_type'], 'idx_plans_status_type');
        });

        Schema::create('plan_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('responsible_org_unit_id')->constrained('org_units')->restrictOnDelete();
            $table->string('item_code')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('item_type', ['activity', 'budget_line', 'supply_line', 'mission_line', 'revenue_line'])->default('activity');
            $table->string('indicator_text')->nullable();
            $table->string('target_text')->nullable();
            $table->string('target_unit')->nullable();
            $table->unsignedSmallInteger('item_year')->nullable();
            $table->unsignedTinyInteger('item_period_no')->nullable();
            $table->string('period_label')->nullable();
            $table->decimal('planned_quantity', 14, 2)->nullable();
            $table->string('planned_unit', 100)->nullable();
            $table->decimal('total_cost', 16, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['plan_id', 'sort_order'], 'idx_plan_items_plan_sort');
            $table->index(['responsible_org_unit_id'], 'idx_plan_items_responsible_unit');
        });

        Schema::create('plan_item_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_item_id')->constrained('plan_items')->cascadeOnDelete();
            $table->unsignedTinyInteger('quarter')->nullable();
            $table->unsignedTinyInteger('month')->nullable();
            $table->unsignedTinyInteger('period_no')->nullable();
            $table->string('period_type', 50)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('planned_quantity', 14, 2)->nullable();
            $table->string('period_label')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['plan_item_id', 'quarter', 'month'], 'idx_plan_item_schedules_period');
        });

        Schema::create('plan_item_costs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_item_id')->constrained('plan_items')->cascadeOnDelete();
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('funding_source_id')->nullable()->constrained('funding_sources')->nullOnDelete();
            $table->string('cost_code')->nullable();
            $table->string('cost_name');
            $table->string('chapter_code', 20);
            $table->string('account_code', 20);
            $table->string('subaccount_code', 20);
            $table->decimal('quantity', 14, 2)->default(1);
            $table->string('unit')->nullable();
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->decimal('total_cost', 16, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['plan_item_id'], 'idx_plan_item_costs_plan_item');
            $table->index(['chapter_code', 'account_code', 'subaccount_code'], 'idx_plan_item_costs_budget_codes');
        });

        Schema::create('plan_item_indicators', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_item_id')->constrained('plan_items')->cascadeOnDelete();
            $table->foreignId('indicator_id')->constrained('indicators')->restrictOnDelete();
            $table->decimal('baseline_value', 14, 2)->nullable();
            $table->decimal('target_value', 14, 2)->nullable();
            $table->decimal('achieved_value', 14, 2)->nullable();
            $table->string('value_text')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['plan_item_id', 'indicator_id'], 'idx_plan_item_indicators_item_indicator');
        });

        Schema::create('plan_personnel_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_item_id')->constrained('plan_items')->cascadeOnDelete();
            $table->string('cadre_name');
            $table->string('cadre_name_km')->nullable();
            $table->unsignedInteger('person_count')->default(0);
            $table->unsignedInteger('days_count')->default(0);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->decimal('total_cost', 16, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['plan_item_id'], 'idx_plan_personnel_lines_item');
        });

        Schema::create('plan_revenue_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('revenue_code')->nullable();
            $table->string('revenue_name');
            $table->foreignId('funding_source_id')->nullable()->constrained('funding_sources')->nullOnDelete();
            $table->decimal('quantity', 14, 2)->nullable();
            $table->string('unit', 100)->nullable();
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('total_amount', 16, 2)->default(0);
            $table->text('note')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index(['plan_id', 'sort_order'], 'idx_plan_revenue_lines_plan_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_revenue_lines');
        Schema::dropIfExists('plan_personnel_lines');
        Schema::dropIfExists('plan_item_indicators');
        Schema::dropIfExists('plan_item_costs');
        Schema::dropIfExists('plan_item_schedules');
        Schema::dropIfExists('plan_items');
        Schema::dropIfExists('plans');
    }
};
