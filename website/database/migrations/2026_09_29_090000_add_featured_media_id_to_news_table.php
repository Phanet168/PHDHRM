<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * News' featured image becomes a Media Library reference (the single
     * source of truth), same pattern as HealthContent's thumbnail_media_id.
     * The legacy `featured_image` path column is kept (nullOnDelete, not
     * dropped) as a fallback for records that predate this column and for
     * MediaManagerService's raw-path "where is this file used" lookups.
     */
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->foreignId('featured_media_id')->nullable()->after('featured_image')
                ->constrained('media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropConstrainedForeignId('featured_media_id');
        });
    }
};
