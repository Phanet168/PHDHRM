<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_km');
            $table->string('name_en')->nullable();
            $table->string('slug')->unique();
            $table->enum('facility_type', ['referral_hospital', 'health_center', 'health_post']);
            $table->string('district');
            $table->string('commune');
            $table->string('village')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('opening_hours')->nullable();
            $table->text('description')->nullable();
            $table->longText('history')->nullable();
            $table->longText('vision')->nullable();
            $table->longText('mission')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('logo')->nullable();
            $table->string('cover_image')->nullable();
            $table->unsignedInteger('patients_count')->default(0);
            $table->unsignedInteger('staff_count')->default(0);
            $table->unsignedInteger('services_count')->default(0);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['facility_type', 'status']);
            $table->index(['district', 'commune']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};
