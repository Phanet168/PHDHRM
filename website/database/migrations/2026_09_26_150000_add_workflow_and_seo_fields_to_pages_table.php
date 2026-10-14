<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->text('excerpt')->nullable()->after('title_en');
            $table->string('template')->default('default')->after('content');
            $table->foreignId('featured_media_id')->nullable()->after('template')->constrained('media')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->after('featured_media_id')->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->after('author_id')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('reviewer_id');
            $table->timestamp('published_at')->nullable()->after('approved_at');
            $table->string('meta_title')->nullable()->after('published_at');
            $table->string('meta_description')->nullable()->after('meta_title');
            $table->string('canonical_url')->nullable()->after('meta_description');
            $table->foreignId('created_by')->nullable()->after('canonical_url')->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->enum('status', ['draft', 'pending_review', 'approved', 'published', 'archived'])->default('draft')->change();
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->enum('status', ['draft', 'published'])->default('draft')->change();
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['meta_title', 'meta_description', 'canonical_url']);
            $table->dropColumn('published_at');
            $table->dropColumn('approved_at');
            $table->dropConstrainedForeignId('reviewer_id');
            $table->dropConstrainedForeignId('author_id');
            $table->dropConstrainedForeignId('featured_media_id');
            $table->dropColumn('template');
            $table->dropColumn('excerpt');
        });
    }
};
