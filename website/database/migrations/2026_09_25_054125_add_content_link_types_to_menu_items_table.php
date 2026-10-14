<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->enum('link_type', ['route', 'url', 'page', 'facility', 'news_category', 'content_category', 'content'])
                ->default('url')->change();
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->enum('link_type', ['route', 'url', 'page', 'facility', 'news_category'])
                ->default('url')->change();
        });
    }
};
