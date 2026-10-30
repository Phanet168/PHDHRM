<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Health activity reporting (port of the PHDReport Firebase app).
 *
 * department (ជំពូក) → unit (ផ្នែក) → indicator, with one value/target per indicator per period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_departments', function (Blueprint $t) {
            $t->id();
            $t->string('code', 64)->unique();
            $t->string('name');
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('report_units', function (Blueprint $t) {
            $t->id();
            $t->foreignId('department_id')->constrained('report_departments')->cascadeOnDelete();
            $t->string('code', 64)->unique();
            $t->string('name');
            $t->string('unit_type', 64)->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('report_indicators', function (Blueprint $t) {
            $t->id();
            $t->string('code', 64)->unique();
            $t->text('name');
            $t->string('indicator_type')->nullable();
            $t->foreignId('department_id')->constrained('report_departments')->cascadeOnDelete();
            $t->foreignId('unit_id')->constrained('report_units')->cascadeOnDelete();
            $t->unsignedBigInteger('owner_user_id')->nullable()->index();
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('report_periods', function (Blueprint $t) {
            $t->id();
            $t->string('code', 16)->unique(); // 2025-M01, 2025-Q1, 2025-H1, 2025-N9, 2025-Y
            $t->unsignedSmallInteger('year');
            $t->string('tag', 8);
            $t->string('name')->nullable();
            $t->boolean('locked')->default(false);
            $t->timestamps();
            $t->index(['year', 'tag']);
        });

        Schema::create('report_values', function (Blueprint $t) {
            $t->id();
            $t->foreignId('period_id')->constrained('report_periods')->cascadeOnDelete();
            $t->foreignId('indicator_id')->constrained('report_indicators')->cascadeOnDelete();
            $t->foreignId('unit_id')->constrained('report_units')->cascadeOnDelete();
            $t->decimal('value', 20, 4)->nullable();
            $t->decimal('target', 20, 4)->nullable();
            $t->decimal('value_hc', 20, 4)->nullable();
            $t->decimal('value_hosp', 20, 4)->nullable();
            $t->string('source', 16)->default('manual'); // manual | import | copy
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
            $t->unique(['period_id', 'indicator_id']);
        });

        Schema::create('report_actions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('period_id')->constrained('report_periods')->cascadeOnDelete();
            $t->foreignId('indicator_id')->constrained('report_indicators')->cascadeOnDelete();
            $t->text('issue_text')->nullable();
            $t->text('action_text')->nullable();
            $t->string('action_owner')->nullable();
            $t->date('action_due')->nullable();
            $t->string('action_status', 16)->default('planned'); // planned | ongoing | done | blocked
            $t->text('resolution_text')->nullable();
            $t->date('resolution_date')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
            $t->index(['period_id', 'indicator_id']);
            $t->index('action_status');
        });

        Schema::create('report_import_mappings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('indicator_id')->unique()->constrained('report_indicators')->cascadeOnDelete();
            $t->string('hc_sheet')->nullable();
            $t->string('hc_cell', 32)->nullable();
            $t->text('hc_formula')->nullable();
            $t->string('hosp_sheet')->nullable();
            $t->string('hosp_cell', 32)->nullable();
            $t->text('hosp_formula')->nullable();
            $t->text('result_formula')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('report_user_units', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->foreignId('unit_id')->constrained('report_units')->cascadeOnDelete();
            $t->boolean('can_edit')->default(true);
            $t->timestamps();
            $t->unique(['user_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_user_units');
        Schema::dropIfExists('report_import_mappings');
        Schema::dropIfExists('report_actions');
        Schema::dropIfExists('report_values');
        Schema::dropIfExists('report_periods');
        Schema::dropIfExists('report_indicators');
        Schema::dropIfExists('report_units');
        Schema::dropIfExists('report_departments');
    }
};
