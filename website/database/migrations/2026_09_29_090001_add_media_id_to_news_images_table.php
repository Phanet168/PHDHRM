<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Same media_id column ContentImage already has, for the same reason: link a gallery row to its Media Library record. */
    public function up(): void
    {
        Schema::table('news_images', function (Blueprint $table) {
            $table->foreignId('media_id')->nullable()->after('news_id')
                ->constrained('media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('news_images', function (Blueprint $table) {
            $table->dropConstrainedForeignId('media_id');
        });
    }
};
