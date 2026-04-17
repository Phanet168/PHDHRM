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
        if (!Schema::hasTable('employee_profile_extras')) {
            return;
        }

        Schema::table('employee_profile_extras', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_profile_extras', 'is_ethnic_minority')) {
                $table->boolean('is_ethnic_minority')->default(false)->after('birth_place_village');
            }

            if (!Schema::hasColumn('employee_profile_extras', 'ethnic_minority_name')) {
                $table->string('ethnic_minority_name', 120)->nullable()->after('is_ethnic_minority');
            }

            if (!Schema::hasColumn('employee_profile_extras', 'ethnic_minority_other')) {
                $table->string('ethnic_minority_other', 120)->nullable()->after('ethnic_minority_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('employee_profile_extras')) {
            return;
        }

        Schema::table('employee_profile_extras', function (Blueprint $table) {
            if (Schema::hasColumn('employee_profile_extras', 'ethnic_minority_other')) {
                $table->dropColumn('ethnic_minority_other');
            }

            if (Schema::hasColumn('employee_profile_extras', 'ethnic_minority_name')) {
                $table->dropColumn('ethnic_minority_name');
            }

            if (Schema::hasColumn('employee_profile_extras', 'is_ethnic_minority')) {
                $table->dropColumn('is_ethnic_minority');
            }
        });
    }
};
