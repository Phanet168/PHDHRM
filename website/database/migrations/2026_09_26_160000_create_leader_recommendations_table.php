<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leader_recommendations', function (Blueprint $table) {
            $table->id();
            $table->string('title_km');
            $table->string('subtitle_km')->nullable();
            $table->string('leader_name_km');
            $table->string('leader_position_km')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('leader_recommendation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recommendation_id')->constrained('leader_recommendations')->cascadeOnDelete();
            $table->text('content_km');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['recommendation_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leader_recommendation_items');
        Schema::dropIfExists('leader_recommendations');
    }
};
