<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_quick_links', function (Blueprint $table) {
            $table->id();
            $table->string('title_km');
            $table->string('description_km')->nullable();
            $table->string('icon');
            $table->enum('link_type', ['route', 'url'])->default('route');
            $table->string('route_name')->nullable();
            $table->string('url')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_quick_links');
    }
};
