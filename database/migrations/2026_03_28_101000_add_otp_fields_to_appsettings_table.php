<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appsettings', function (Blueprint $table) {
            if (!Schema::hasColumn('appsettings', 'otp_required')) {
                $table->boolean('otp_required')->nullable()->after('googleapi_authkey');
            }

            if (!Schema::hasColumn('appsettings', 'otp_channel')) {
                $table->string('otp_channel', 20)->nullable()->after('otp_required');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appsettings', function (Blueprint $table) {
            if (Schema::hasColumn('appsettings', 'otp_channel')) {
                $table->dropColumn('otp_channel');
            }

            if (Schema::hasColumn('appsettings', 'otp_required')) {
                $table->dropColumn('otp_required');
            }
        });
    }
};

