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
            if (!Schema::hasColumn('employee_profile_extras', 'telegram_account')) {
                $table->string('telegram_account', 191)->nullable()->after('institution_email');
            }
            if (!Schema::hasColumn('employee_profile_extras', 'facebook_account')) {
                $table->string('facebook_account', 191)->nullable()->after('telegram_account');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_profile_extras', function (Blueprint $table) {
            if (Schema::hasColumn('employee_profile_extras', 'facebook_account')) {
                $table->dropColumn('facebook_account');
            }
            if (Schema::hasColumn('employee_profile_extras', 'telegram_account')) {
                $table->dropColumn('telegram_account');
            }
        });
    }
};

