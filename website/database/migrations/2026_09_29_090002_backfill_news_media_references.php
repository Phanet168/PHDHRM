<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Every existing news.featured_image / news_images.image was already
     * uploaded through MediaUploader::store(), which always creates a
     * matching Media row — it just was never linked. This links them by
     * matching disk_path, so no new Media rows and no re-uploading.
     */
    public function up(): void
    {
        $mediaByPath = DB::table('media')->whereNull('deleted_at')->pluck('id', 'disk_path');

        if ($mediaByPath->isEmpty()) {
            return;
        }

        foreach (DB::table('news')->whereNotNull('featured_image')->whereNull('featured_media_id')->get(['id', 'featured_image']) as $row) {
            if ($mediaId = $mediaByPath->get($row->featured_image)) {
                DB::table('news')->where('id', $row->id)->update(['featured_media_id' => $mediaId]);
            }
        }

        foreach (DB::table('news_images')->whereNotNull('image')->whereNull('media_id')->get(['id', 'image']) as $row) {
            if ($mediaId = $mediaByPath->get($row->image)) {
                DB::table('news_images')->where('id', $row->id)->update(['media_id' => $mediaId]);
            }
        }
    }

    /** A data backfill; nothing to structurally revert. */
    public function down(): void {}
};
