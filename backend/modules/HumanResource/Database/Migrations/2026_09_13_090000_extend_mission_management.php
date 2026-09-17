<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable();
            $table->unsignedBigInteger('started_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
        });
        Schema::create('mission_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
            $table->string('name');
            $table->string('path', 1000);
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size');
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamps();
        });
        Schema::create('mission_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedBigInteger('submitted_by');
            $table->text('summary');
            $table->timestamps();
            $table->unique(['mission_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_reports');
        Schema::dropIfExists('mission_documents');
        Schema::table('missions', fn (Blueprint $table) => $table->dropColumn(['started_at', 'started_by', 'completed_at', 'completed_by']));
    }
};
