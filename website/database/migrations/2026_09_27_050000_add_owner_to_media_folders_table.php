<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_folders', function (Blueprint $table) {
            $table->string('owner_type')->nullable()->after('created_by');
            $table->unsignedBigInteger('owner_id')->nullable()->after('owner_type');
            $table->unique(['owner_type', 'owner_id'], 'media_folders_owner_unique');
        });
    }

    public function down(): void
    {
        Schema::table('media_folders', function (Blueprint $table) {
            $table->dropUnique('media_folders_owner_unique');
            $table->dropColumn(['owner_type', 'owner_id']);
        });
    }
};
