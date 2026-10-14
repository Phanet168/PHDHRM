<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()->after('id')->constrained('media_folders')->nullOnDelete();
            $table->unsignedInteger('width')->nullable()->after('size');
            $table->unsignedInteger('height')->nullable()->after('width');
            $table->string('alt_text')->nullable()->after('height');
            $table->text('caption')->nullable()->after('alt_text');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_id');
            $table->dropColumn(['width', 'height', 'alt_text', 'caption', 'deleted_at']);
        });
    }
};
