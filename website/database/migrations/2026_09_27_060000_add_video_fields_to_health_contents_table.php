<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('health_contents', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('featured_image');
            $table->string('video_source')->nullable()->after('is_featured');
            $table->string('video_url')->nullable()->after('video_source');
            $table->foreignId('video_media_id')->nullable()->after('video_url')->constrained('media')->nullOnDelete();
            $table->foreignId('thumbnail_media_id')->nullable()->after('video_media_id')->constrained('media')->nullOnDelete();
            $table->unsignedInteger('duration_seconds')->nullable()->after('thumbnail_media_id');
        });
    }

    public function down(): void
    {
        Schema::table('health_contents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('video_media_id');
            $table->dropConstrainedForeignId('thumbnail_media_id');
            $table->dropColumn(['is_featured', 'video_source', 'video_url', 'duration_seconds']);
        });
    }
};
