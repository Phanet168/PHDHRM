<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('label_km');
            $table->string('label_en')->nullable();
            $table->enum('link_type', ['route', 'url', 'page', 'facility', 'news_category'])->default('url');
            $table->string('route_name')->nullable();
            $table->string('url')->nullable();
            $table->string('linkable_type')->nullable();
            $table->unsignedBigInteger('linkable_id')->nullable();
            $table->string('icon')->nullable();
            $table->enum('target', ['_self', '_blank'])->default('_self');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'sort_order']);
            $table->index(['linkable_type', 'linkable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
