<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Article attachments (PDF/DOC/XLS…), kept separate from the image gallery so admins are
     * never confused between the two. Always a Media Library reference — no raw path column,
     * unlike the older News image tables, since this is a brand new feature (no legacy rows).
     */
    public function up(): void
    {
        Schema::create('news_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['news_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_attachments');
    }
};
