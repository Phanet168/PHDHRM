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
        if (Schema::hasTable('professional_skills') && !Schema::hasColumn('professional_skills', 'budget_amount')) {
            Schema::table('professional_skills', function (Blueprint $table) {
                $table->decimal('budget_amount', 15, 2)->default(0)->after('retire_age');
            });
        }

        if (Schema::hasTable('positions') && !Schema::hasColumn('positions', 'budget_amount')) {
            Schema::table('positions', function (Blueprint $table) {
                $table->decimal('budget_amount', 15, 2)->default(0)->after('position_rank');
            });
        }

        if (Schema::hasTable('gov_pay_levels') && !Schema::hasColumn('gov_pay_levels', 'budget_amount')) {
            Schema::table('gov_pay_levels', function (Blueprint $table) {
                $table->decimal('budget_amount', 15, 2)->default(0)->after('level_name_km');
                $table->decimal('spouse_allowance_amount', 15, 2)->default(0)->after('budget_amount');
                $table->decimal('child_allowance_amount', 15, 2)->default(0)->after('spouse_allowance_amount');
            });
        }

        if (Schema::hasTable('employees') && !Schema::hasColumn('employees', 'spouse_count')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->unsignedSmallInteger('spouse_count')->nullable()->default(0)->after('no_of_kids');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('professional_skills') && Schema::hasColumn('professional_skills', 'budget_amount')) {
            Schema::table('professional_skills', function (Blueprint $table) {
                $table->dropColumn('budget_amount');
            });
        }

        if (Schema::hasTable('positions') && Schema::hasColumn('positions', 'budget_amount')) {
            Schema::table('positions', function (Blueprint $table) {
                $table->dropColumn('budget_amount');
            });
        }

        if (Schema::hasTable('gov_pay_levels')) {
            $columns = [];
            foreach (['budget_amount', 'spouse_allowance_amount', 'child_allowance_amount'] as $column) {
                if (Schema::hasColumn('gov_pay_levels', $column)) {
                    $columns[] = $column;
                }
            }

            if (!empty($columns)) {
                Schema::table('gov_pay_levels', function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
                });
            }
        }

        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'spouse_count')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('spouse_count');
            });
        }
    }
};

