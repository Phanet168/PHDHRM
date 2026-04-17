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
        Schema::create('professional_skills', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 20)->nullable()->unique();
            $table->string('name_en', 120);
            $table->string('name_km', 120)->nullable();
            $table->string('shortcut_en', 20)->nullable();
            $table->string('shortcut_km', 20)->nullable();
            $table->unsignedTinyInteger('retire_age')->nullable();
            $table->boolean('is_active')->default(true);
            $table->updateCreatedBy();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professional_skills');
    }
};
