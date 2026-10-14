<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('health_content_categories')->nullOnDelete();
            $table->string('title_km');
            $table->string('title_en')->nullable();
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('featured_image')->nullable();
            $table->enum('status', ['draft', 'pending_review', 'approved', 'published', 'archived'])->default('draft');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_contents');
    }
};
