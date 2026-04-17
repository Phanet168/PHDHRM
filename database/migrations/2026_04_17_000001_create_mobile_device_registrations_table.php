<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_device_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('device_id', 191)->comment('Unique hardware ID from device (e.g. Android ID)');
            $table->string('device_name', 191)->nullable()->comment('Human-readable device label');
            $table->string('platform', 30)->nullable()->comment('android|ios|web');
            $table->string('imei', 50)->nullable()->comment('IMEI or serial number reported by device');
            $table->string('fingerprint', 255)->nullable()->comment('Device fingerprint hash');
            $table->enum('status', ['pending', 'active', 'blocked', 'rejected'])->default('pending');
            $table->unsignedBigInteger('blocked_by')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->string('register_ip', 45)->nullable();
            $table->text('register_ua')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'device_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_device_registrations');
    }
};
