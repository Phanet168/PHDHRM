<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_attendance_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->uuid('request_id');
            $table->unsignedBigInteger('attendance_id')->index();
            $table->timestamps();
            $table->unique(['employee_id', 'request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_attendance_requests');
    }
};
