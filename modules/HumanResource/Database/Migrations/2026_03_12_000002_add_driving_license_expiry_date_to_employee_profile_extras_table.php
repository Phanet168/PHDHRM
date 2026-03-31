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
        Schema::table('employee_profile_extras', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_profile_extras', 'driving_license_expiry_date')) {
                $table->date('driving_license_expiry_date')
                    ->nullable()
                    ->after('national_id_expiry_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_profile_extras', function (Blueprint $table) {
            if (Schema::hasColumn('employee_profile_extras', 'driving_license_expiry_date')) {
                $table->dropColumn('driving_license_expiry_date');
            }
        });
    }
};

