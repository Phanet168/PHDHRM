<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structured About-page content. Only what had no existing home is added here:
 * the hero image stays on page_banners, leaders on facility_leaders, contact
 * and map on settings, and hospitals / operational districts on facilities.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Singleton sections keyed hero / history / mission.
        Schema::create('about_sections', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();
            $table->string('title')->nullable();
            $table->string('subtitle', 500)->nullable();
            $table->text('body')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('image_position', 10)->default('right');
            $table->string('icon_key', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_demo')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Repeatable records: core cards, timeline milestones and related documents.
        Schema::create('about_items', function (Blueprint $table) {
            $table->id();
            $table->string('group', 20);
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('date_label', 50)->nullable();
            $table->string('icon_key', 40)->nullable();
            $table->string('accent', 20)->nullable();
            $table->string('link_url', 2048)->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('legal_document_id')->nullable()->constrained('legal_documents')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['group', 'is_active', 'sort_order']);
        });

        Schema::create('about_org_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('about_org_units')->nullOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->string('name');
            $table->string('type', 30)->default('office');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_active', 'sort_order']);
        });

        // Department leaders (facility_id NULL) already live here; extend rather than duplicate.
        Schema::table('facility_leaders', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
            $table->string('title_en')->nullable()->after('title');
            $table->foreignId('portrait_media_id')->nullable()->after('photo')->constrained('media')->nullOnDelete();
            $table->text('bio')->nullable()->after('portrait_media_id');
            $table->boolean('is_active')->default(true)->after('sort_order');
            $table->boolean('is_featured')->default(false)->after('is_active');
            $table->boolean('is_demo')->default(false)->after('is_featured');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('facility_leaders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('portrait_media_id');
            $table->dropColumn(['name_en', 'title_en', 'bio', 'is_active', 'is_featured', 'is_demo', 'deleted_at']);
        });
        Schema::dropIfExists('about_org_units');
        Schema::dropIfExists('about_items');
        Schema::dropIfExists('about_sections');
    }
};
