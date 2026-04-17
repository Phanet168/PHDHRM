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
        Schema::table('employee_pay_grade_histories', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_pay_grade_histories', 'pay_level_id')) {
                $table->foreignId('pay_level_id')
                    ->nullable()
                    ->after('employee_id')
                    ->constrained('gov_pay_levels')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('employee_pay_grade_histories', 'promotion_type')) {
                $table->string('promotion_type', 30)->nullable()->after('status');
            }

            if (!Schema::hasColumn('employee_pay_grade_histories', 'document_reference')) {
                $table->string('document_reference', 191)->nullable()->after('promotion_type');
            }

            if (!Schema::hasColumn('employee_pay_grade_histories', 'document_date')) {
                $table->date('document_date')->nullable()->after('document_reference');
            }

            if (!Schema::hasColumn('employee_pay_grade_histories', 'next_review_date')) {
                $table->date('next_review_date')->nullable()->after('document_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_pay_grade_histories', function (Blueprint $table) {
            if (Schema::hasColumn('employee_pay_grade_histories', 'pay_level_id')) {
                $table->dropConstrainedForeignId('pay_level_id');
            }
            if (Schema::hasColumn('employee_pay_grade_histories', 'promotion_type')) {
                $table->dropColumn('promotion_type');
            }
            if (Schema::hasColumn('employee_pay_grade_histories', 'document_reference')) {
                $table->dropColumn('document_reference');
            }
            if (Schema::hasColumn('employee_pay_grade_histories', 'document_date')) {
                $table->dropColumn('document_date');
            }
            if (Schema::hasColumn('employee_pay_grade_histories', 'next_review_date')) {
                $table->dropColumn('next_review_date');
            }
        });
    }
};

