<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('health_contents', function (Blueprint $table) {
            // video_source (url|media) is replaced outright by video_type
            // (youtube|facebook|uploaded) — no real row uses it yet, so this
            // is a clean drop, not a data migration.
            $table->dropColumn('video_source');
        });

        Schema::table('health_contents', function (Blueprint $table) {
            $table->string('video_type')->nullable()->after('is_featured');

            // Derived-only (see HealthContent::booted()): never set directly
            // from a request, always recomputed from video_type/video_url/
            // video_media_id so the homepage's scopeWithVideo() gate can
            // never go stale.
            $table->string('content_type')->default('article')->after('video_type');

            $table->string('title_original')->nullable()->after('title_en');
            $table->text('educational_message')->nullable()->after('content');
            $table->string('summary_status')->nullable()->after('educational_message');

            $table->string('youtube_video_id')->nullable()->unique()->after('duration_seconds');
            $table->string('external_thumbnail_url')->nullable()->after('youtube_video_id');

            $table->string('source_name')->nullable()->after('external_thumbnail_url');
            $table->string('source_channel_url')->nullable()->after('source_name');
            $table->string('source_video_url')->nullable()->after('source_channel_url');
            $table->text('source_description')->nullable()->after('source_video_url');
            $table->dateTime('source_published_at')->nullable()->after('source_description');

            $table->foreignId('updated_by')->nullable()->after('author_id')->constrained('users')->nullOnDelete();

            $table->index('content_type');
        });
    }

    public function down(): void
    {
        Schema::table('health_contents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropIndex(['content_type']);
            $table->dropColumn([
                'video_type', 'content_type', 'title_original', 'educational_message',
                'summary_status', 'youtube_video_id', 'external_thumbnail_url',
                'source_name', 'source_channel_url', 'source_video_url',
                'source_description', 'source_published_at',
            ]);
        });

        Schema::table('health_contents', function (Blueprint $table) {
            $table->string('video_source')->nullable()->after('is_featured');
        });
    }
};
