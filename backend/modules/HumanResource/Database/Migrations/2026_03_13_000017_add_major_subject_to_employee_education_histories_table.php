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
        if (!Schema::hasTable('employee_education_histories')) {
            return;
        }

        Schema::table('employee_education_histories', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_education_histories', 'major_subject')) {
                $table->string('major_subject', 191)->nullable()->after('degree_level');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('employee_education_histories')) {
            return;
        }

        Schema::table('employee_education_histories', function (Blueprint $table) {
            if (Schema::hasColumn('employee_education_histories', 'major_subject')) {
                $table->dropColumn('major_subject');
            }
        });
    }
};

