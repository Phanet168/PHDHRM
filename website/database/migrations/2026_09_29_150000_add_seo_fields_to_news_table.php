<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Same SEO fields Content and Page already have — mirrored onto News for its new SEO tab. */
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('published_at');
            $table->string('meta_description')->nullable()->after('meta_title');
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description']);
        });
    }
};
