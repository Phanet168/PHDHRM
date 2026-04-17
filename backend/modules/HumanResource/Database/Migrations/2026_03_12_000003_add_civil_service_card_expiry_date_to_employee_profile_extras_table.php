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
            if (!Schema::hasColumn('employee_profile_extras', 'civil_service_card_expiry_date')) {
                $table->date('civil_service_card_expiry_date')
                    ->nullable()
                    ->after('driving_license_expiry_date');
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
            if (Schema::hasColumn('employee_profile_extras', 'civil_service_card_expiry_date')) {
                $table->dropColumn('civil_service_card_expiry_date');
            }
        });
    }
};
