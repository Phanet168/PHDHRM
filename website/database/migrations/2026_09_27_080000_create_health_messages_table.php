<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_km');
            $table->string('icon');
            $table->string('accent_color')->nullable();
            $table->string('source_name')->nullable();
            $table->string('source_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_messages');
    }
};
