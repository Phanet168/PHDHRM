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
        Schema::create('attendance_scan_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('workplace_id')->nullable()->index();
            $table->string('status', 40)->default('error')->index();
            $table->string('error_code', 80)->nullable()->index();
            $table->text('message')->nullable();
            $table->decimal('range_meters', 10, 1)->nullable();
            $table->decimal('acceptable_range_meters', 10, 1)->nullable();
            $table->string('geofence_source', 40)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('qr_token_hash', 64)->nullable()->index();
            $table->json('meta_payload')->nullable();
            $table->dateTime('scanned_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_scan_logs');
    }
};
