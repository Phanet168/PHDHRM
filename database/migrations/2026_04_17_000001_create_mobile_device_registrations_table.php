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
            $table->enum('status', ['active', 'blocked'])->default('active');
            $table->unsignedBigInteger('blocked_by')->nullable();
            $table->timestamp('blocked_at')->nullable();
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
