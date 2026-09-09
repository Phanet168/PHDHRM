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
        if (!Schema::hasTable('employee_academic_infos')) {
            return;
        }

        Schema::table('employee_academic_infos', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_academic_infos', 'certificate_type')) {
                $table->string('certificate_type', 120)->nullable()->after('exam_title');
            }

            if (!Schema::hasColumn('employee_academic_infos', 'certificate_type_other')) {
                $table->string('certificate_type_other', 191)->nullable()->after('certificate_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('employee_academic_infos')) {
            return;
        }

        Schema::table('employee_academic_infos', function (Blueprint $table) {
            if (Schema::hasColumn('employee_academic_infos', 'certificate_type_other')) {
                $table->dropColumn('certificate_type_other');
            }

            if (Schema::hasColumn('employee_academic_infos', 'certificate_type')) {
                $table->dropColumn('certificate_type');
            }
        });
    }
};
