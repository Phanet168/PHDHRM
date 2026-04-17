<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend enum to include pending/rejected via raw SQL (avoids doctrine/dbal)
        DB::statement("ALTER TABLE mobile_device_registrations MODIFY COLUMN status ENUM('pending','active','blocked','rejected') NOT NULL DEFAULT 'pending'");

        Schema::table('mobile_device_registrations', function (Blueprint $table) {
            $table->string('imei', 50)->nullable()->after('platform')
                  ->comment('IMEI or serial number reported by device');
            $table->string('fingerprint', 255)->nullable()->after('imei')
                  ->comment('Device fingerprint hash');
            $table->unsignedBigInteger('approved_by')->nullable()->after('blocked_at');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->unsignedBigInteger('rejected_by')->nullable()->after('approved_at');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->string('rejection_reason', 255)->nullable()->after('rejected_at');
            $table->string('register_ip', 45)->nullable()->after('rejection_reason');
            $table->text('register_ua')->nullable()->after('register_ip');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_device_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'imei', 'fingerprint',
                'approved_by', 'approved_at',
                'rejected_by', 'rejected_at', 'rejection_reason',
                'register_ip', 'register_ua',
            ]);
        });
        DB::statement("ALTER TABLE mobile_device_registrations MODIFY COLUMN status ENUM('active','blocked') NOT NULL DEFAULT 'active'");
    }
};
