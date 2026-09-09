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
        if (Schema::hasTable('employee_education_histories')) {
            Schema::table('employee_education_histories', function (Blueprint $table) {
                if (!Schema::hasColumn('employee_education_histories', 'country_name')) {
                    $table->string('country_name', 120)->nullable()->after('employee_id');
                }
            });
        }

        if (Schema::hasTable('employee_foreign_languages')) {
            Schema::table('employee_foreign_languages', function (Blueprint $table) {
                if (!Schema::hasColumn('employee_foreign_languages', 'country_name')) {
                    $table->string('country_name', 120)->nullable()->after('employee_id');
                }
            });
        }

        if (Schema::hasTable('employee_academic_infos')) {
            Schema::table('employee_academic_infos', function (Blueprint $table) {
                if (!Schema::hasColumn('employee_academic_infos', 'country_name')) {
                    $table->string('country_name', 120)->nullable()->after('employee_id');
                }
                if (!Schema::hasColumn('employee_academic_infos', 'start_date')) {
                    $table->date('start_date')->nullable()->after('result');
                }
                if (!Schema::hasColumn('employee_academic_infos', 'end_date')) {
                    $table->date('end_date')->nullable()->after('start_date');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('employee_academic_infos')) {
            Schema::table('employee_academic_infos', function (Blueprint $table) {
                if (Schema::hasColumn('employee_academic_infos', 'end_date')) {
                    $table->dropColumn('end_date');
                }
                if (Schema::hasColumn('employee_academic_infos', 'start_date')) {
                    $table->dropColumn('start_date');
                }
                if (Schema::hasColumn('employee_academic_infos', 'country_name')) {
                    $table->dropColumn('country_name');
                }
            });
        }

        if (Schema::hasTable('employee_foreign_languages')) {
            Schema::table('employee_foreign_languages', function (Blueprint $table) {
                if (Schema::hasColumn('employee_foreign_languages', 'country_name')) {
                    $table->dropColumn('country_name');
                }
            });
        }

        if (Schema::hasTable('employee_education_histories')) {
            Schema::table('employee_education_histories', function (Blueprint $table) {
                if (Schema::hasColumn('employee_education_histories', 'country_name')) {
                    $table->dropColumn('country_name');
                }
            });
        }
    }
};
