<?php

use App\Http\Controllers\Admin\AboutController;
use App\Http\Controllers\Admin\AboutItemController;
use App\Http\Controllers\Admin\AboutLeaderController;
use App\Http\Controllers\Admin\AboutOrgUnitController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ContentBlockController;
use App\Http\Controllers\Admin\ContentCategoryController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\ContentImageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FacilityController;
use App\Http\Controllers\Admin\HealthContentController;
use App\Http\Controllers\Admin\HealthMessageController;
use App\Http\Controllers\Admin\HeroSlideController;
use App\Http\Controllers\Admin\HomepageQuickLinkController;
use App\Http\Controllers\Admin\LeaderRecommendationController;
use App\Http\Controllers\Admin\LeaderRecommendationItemController;
use App\Http\Controllers\Admin\LegalDocumentController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MediaFolderController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\NewsAttachmentController;
use App\Http\Controllers\Admin\NewsController;
use App\Http\Controllers\Admin\NewsImageController;
use App\Http\Controllers\Admin\PageBannerController;
use App\Http\Controllers\Admin\PageBlockController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\YouTubeImportController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    });

    Route::middleware(['auth', 'active'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/dashboard', DashboardController::class)->name('dashboard.alias');

        Route::resource('facilities', FacilityController::class);
        Route::post('facilities/{facility}/publish', [FacilityController::class, 'publish'])->name('facilities.publish');
        Route::post('facilities/{facility}/archive', [FacilityController::class, 'archive'])->name('facilities.archive');
        Route::post('facilities/{facility}/services', [FacilityController::class, 'storeService'])->name('facilities.services.store');
        Route::delete('facilities/{facility}/services/{service}', [FacilityController::class, 'destroyService'])->name('facilities.services.destroy');
        Route::post('facilities/{facility}/leaders', [FacilityController::class, 'storeLeader'])->name('facilities.leaders.store');
        Route::delete('facilities/{facility}/leaders/{leader}', [FacilityController::class, 'destroyLeader'])->name('facilities.leaders.destroy');
        Route::post('facilities/{facility}/partners', [FacilityController::class, 'syncPartners'])->name('facilities.partners.sync');
        Route::post('facilities/{facility}/gallery', [FacilityController::class, 'storeGalleryImage'])->name('facilities.gallery.store');
        Route::delete('facilities/{facility}/gallery/{image}', [FacilityController::class, 'destroyGalleryImage'])->name('facilities.gallery.destroy');

        Route::resource('news', NewsController::class);
        Route::post('news/{news}/submit', [NewsController::class, 'submit'])->name('news.submit');
        Route::post('news/{news}/approve', [NewsController::class, 'approve'])->name('news.approve');
        Route::post('news/{news}/publish', [NewsController::class, 'publish'])->name('news.publish');
        Route::post('news/{news}/archive', [NewsController::class, 'archive'])->name('news.archive');
        Route::post('news/{news}/restore', [NewsController::class, 'restore'])->name('news.restore')->withTrashed();
        Route::delete('news/{news}/force', [NewsController::class, 'forceDestroy'])->name('news.force-destroy')->withTrashed();
        Route::post('news/{news}/images/from-media', [NewsImageController::class, 'attachFromMedia'])->name('news.images.from-media');
        Route::put('news/{news}/images/{image}', [NewsImageController::class, 'update'])->name('news.images.update');
        Route::delete('news/{news}/images/{image}', [NewsImageController::class, 'destroy'])->name('news.images.destroy');
        Route::post('news/{news}/images/reorder', [NewsImageController::class, 'reorder'])->name('news.images.reorder');
        Route::post('news/{news}/attachments/from-media', [NewsAttachmentController::class, 'attachFromMedia'])->name('news.attachments.from-media');
        Route::put('news/{news}/attachments/{attachment}', [NewsAttachmentController::class, 'update'])->name('news.attachments.update');
        Route::delete('news/{news}/attachments/{attachment}', [NewsAttachmentController::class, 'destroy'])->name('news.attachments.destroy');
        Route::post('news/{news}/attachments/reorder', [NewsAttachmentController::class, 'reorder'])->name('news.attachments.reorder');

        Route::resource('legal-documents', LegalDocumentController::class);
        Route::post('legal-documents/{legal_document}/publish', [LegalDocumentController::class, 'publish'])->name('legal-documents.publish');
        Route::post('legal-documents/{legal_document}/archive', [LegalDocumentController::class, 'archive'])->name('legal-documents.archive');

        // Registered before the resource route below: a literal
        // "youtube-import" segment would otherwise be swallowed by the
        // resource's GET health-content/{health_content} wildcard.
        Route::get('health-content/youtube-import', [YouTubeImportController::class, 'create'])->name('health-content.youtube-import.create');
        Route::post('health-content/youtube-import', [YouTubeImportController::class, 'import'])->name('health-content.youtube-import.import');
        Route::post('health-content/youtube-import/search', [YouTubeImportController::class, 'search'])->name('health-content.youtube-import.search');

        Route::resource('health-content', HealthContentController::class);
        Route::post('health-content/{health_content}/submit', [HealthContentController::class, 'submit'])->name('health-content.submit');
        Route::post('health-content/{health_content}/approve', [HealthContentController::class, 'approve'])->name('health-content.approve');
        Route::post('health-content/{health_content}/publish', [HealthContentController::class, 'publish'])->name('health-content.publish');
        Route::post('health-content/{health_content}/archive', [HealthContentController::class, 'archive'])->name('health-content.archive');
        Route::post('health-content/{health_content}/refresh-metadata', [YouTubeImportController::class, 'refreshMetadata'])->name('health-content.refresh-metadata');
        Route::post('health-content/{health_content}/save-thumbnail', [YouTubeImportController::class, 'saveThumbnail'])->name('health-content.save-thumbnail');

        Route::resource('partners', PartnerController::class)->except(['show']);

        Route::resource('hero-slides', HeroSlideController::class)->except(['show']);

        Route::resource('homepage-quick-links', HomepageQuickLinkController::class)->except(['show']);
        Route::post('homepage-quick-links/{homepageQuickLink}/toggle', [HomepageQuickLinkController::class, 'toggle'])->name('homepage-quick-links.toggle');

        Route::resource('health-messages', HealthMessageController::class)->except(['show']);

        // About page (អំពីមន្ទីរ): overview, singleton sections, repeatable items, leaders, organization, contact.
        Route::prefix('about')->name('about.')->group(function () {
            Route::get('/', [AboutController::class, 'index'])->name('index');
            Route::get('sections/{key}/edit', [AboutController::class, 'editSection'])->whereIn('key', ['hero', 'history', 'mission'])->name('sections.edit');
            Route::put('sections/{key}', [AboutController::class, 'updateSection'])->whereIn('key', ['hero', 'history', 'mission'])->name('sections.update');
            Route::get('contact', [AboutController::class, 'editContact'])->name('contact.edit');
            Route::put('contact', [AboutController::class, 'updateContact'])->name('contact.update');

            Route::get('leaders', [AboutLeaderController::class, 'index'])->name('leaders.index');
            Route::get('leaders/create', [AboutLeaderController::class, 'create'])->name('leaders.create');
            Route::post('leaders', [AboutLeaderController::class, 'store'])->name('leaders.store');
            Route::post('leaders/reorder', [AboutLeaderController::class, 'reorder'])->name('leaders.reorder');
            Route::get('leaders/{leader}/edit', [AboutLeaderController::class, 'edit'])->name('leaders.edit');
            Route::put('leaders/{leader}', [AboutLeaderController::class, 'update'])->name('leaders.update');
            Route::post('leaders/{leader}/toggle', [AboutLeaderController::class, 'toggle'])->name('leaders.toggle');
            Route::delete('leaders/{leader}', [AboutLeaderController::class, 'destroy'])->name('leaders.destroy');
            Route::post('leaders/{leader}/restore', [AboutLeaderController::class, 'restore'])->whereNumber('leader')->name('leaders.restore');
            Route::delete('leaders/{leader}/force', [AboutLeaderController::class, 'forceDestroy'])->whereNumber('leader')->name('leaders.force-destroy');

            Route::get('organization', [AboutOrgUnitController::class, 'index'])->name('organization.index');
            Route::get('organization/create', [AboutOrgUnitController::class, 'create'])->name('organization.create');
            Route::post('organization', [AboutOrgUnitController::class, 'store'])->name('organization.store');
            Route::post('organization/reorder', [AboutOrgUnitController::class, 'reorder'])->name('organization.reorder');
            Route::post('organization/import-facilities', [AboutOrgUnitController::class, 'importFacilities'])->name('organization.import');
            Route::get('organization/{unit}/edit', [AboutOrgUnitController::class, 'edit'])->name('organization.edit');
            Route::put('organization/{unit}', [AboutOrgUnitController::class, 'update'])->name('organization.update');
            Route::post('organization/{unit}/toggle', [AboutOrgUnitController::class, 'toggle'])->name('organization.toggle');
            Route::delete('organization/{unit}', [AboutOrgUnitController::class, 'destroy'])->name('organization.destroy');
            Route::post('organization/{unit}/restore', [AboutOrgUnitController::class, 'restore'])->whereNumber('unit')->name('organization.restore');
            Route::delete('organization/{unit}/force', [AboutOrgUnitController::class, 'forceDestroy'])->whereNumber('unit')->name('organization.force-destroy');

            Route::prefix('{group}')->whereIn('group', ['cards', 'timeline', 'documents'])->name('items.')->group(function () {
                Route::get('/', [AboutItemController::class, 'index'])->name('index');
                Route::get('create', [AboutItemController::class, 'create'])->name('create');
                Route::post('/', [AboutItemController::class, 'store'])->name('store');
                Route::post('reorder', [AboutItemController::class, 'reorder'])->name('reorder');
                Route::get('{item}/edit', [AboutItemController::class, 'edit'])->name('edit');
                Route::put('{item}', [AboutItemController::class, 'update'])->name('update');
                Route::post('{item}/toggle', [AboutItemController::class, 'toggle'])->name('toggle');
                Route::delete('{item}', [AboutItemController::class, 'destroy'])->name('destroy');
                Route::post('{item}/restore', [AboutItemController::class, 'restore'])->whereNumber('item')->name('restore');
                Route::delete('{item}/force', [AboutItemController::class, 'forceDestroy'])->whereNumber('item')->name('force-destroy');
            });
        });

        Route::get('page-banners', [PageBannerController::class, 'index'])->name('page-banners.index');
        Route::get('page-banners/{pageKey}/edit', [PageBannerController::class, 'edit'])->name('page-banners.edit');
        Route::put('page-banners/{pageKey}', [PageBannerController::class, 'update'])->name('page-banners.update');

        Route::resource('leader-recommendations', LeaderRecommendationController::class)->except(['show']);
        Route::post('leader-recommendations/{leaderRecommendation}/toggle', [LeaderRecommendationController::class, 'toggle'])->name('leader-recommendations.toggle');
        Route::post('leader-recommendations/{leaderRecommendation}/publish', [LeaderRecommendationController::class, 'publish'])->name('leader-recommendations.publish');
        Route::post('leader-recommendations/{leaderRecommendation}/unpublish', [LeaderRecommendationController::class, 'unpublish'])->name('leader-recommendations.unpublish');
        Route::post('leader-recommendations/{leaderRecommendation}/items', [LeaderRecommendationItemController::class, 'store'])->name('leader-recommendations.items.store');
        Route::put('leader-recommendations/{leaderRecommendation}/items/{item}', [LeaderRecommendationItemController::class, 'update'])->name('leader-recommendations.items.update');
        Route::delete('leader-recommendations/{leaderRecommendation}/items/{item}', [LeaderRecommendationItemController::class, 'destroy'])->name('leader-recommendations.items.destroy');
        Route::post('leader-recommendations/{leaderRecommendation}/items/{item}/toggle', [LeaderRecommendationItemController::class, 'toggle'])->name('leader-recommendations.items.toggle');
        Route::post('leader-recommendations/{leaderRecommendation}/items/reorder', [LeaderRecommendationItemController::class, 'reorder'])->name('leader-recommendations.items.reorder');

        Route::resource('menus', MenuController::class)->except(['show']);
        Route::get('menus/{menu}/items/create', [MenuItemController::class, 'create'])->name('menus.items.create');
        Route::post('menus/{menu}/items', [MenuItemController::class, 'store'])->name('menus.items.store');
        Route::get('menus/{menu}/items/{item}/edit', [MenuItemController::class, 'edit'])->name('menus.items.edit');
        Route::put('menus/{menu}/items/{item}', [MenuItemController::class, 'update'])->name('menus.items.update');
        Route::delete('menus/{menu}/items/{item}', [MenuItemController::class, 'destroy'])->name('menus.items.destroy');
        Route::post('menus/{menu}/items/{item}/toggle', [MenuItemController::class, 'toggle'])->name('menus.items.toggle');
        Route::post('menus/{menu}/items/reorder', [MenuItemController::class, 'reorder'])->name('menus.items.reorder');

        Route::resource('content-categories', ContentCategoryController::class)->except(['show']);

        Route::resource('content', ContentController::class)->except(['show']);
        Route::post('content/{content}/submit', [ContentController::class, 'submit'])->name('content.submit');
        Route::post('content/{content}/approve', [ContentController::class, 'approve'])->name('content.approve');
        Route::post('content/{content}/publish', [ContentController::class, 'publish'])->name('content.publish');
        Route::post('content/{content}/archive', [ContentController::class, 'archive'])->name('content.archive');

        Route::post('content/{content}/images', [ContentImageController::class, 'store'])->name('content.images.store');
        Route::post('content/{content}/images/from-media', [ContentImageController::class, 'attachFromMedia'])->name('content.images.from-media');
        Route::put('content/{content}/images/{image}', [ContentImageController::class, 'update'])->name('content.images.update');
        Route::post('content/{content}/images/{image}/feature', [ContentImageController::class, 'setFeatured'])->name('content.images.feature');
        Route::delete('content/{content}/images/{image}', [ContentImageController::class, 'destroy'])->name('content.images.destroy');
        Route::post('content/{content}/images/reorder', [ContentImageController::class, 'reorder'])->name('content.images.reorder');

        Route::post('content/{content}/blocks', [ContentBlockController::class, 'store'])->name('content.blocks.store');
        Route::put('content/{content}/blocks/{block}', [ContentBlockController::class, 'update'])->name('content.blocks.update');
        Route::post('content/{content}/blocks/{block}/duplicate', [ContentBlockController::class, 'duplicate'])->name('content.blocks.duplicate');
        Route::post('content/{content}/blocks/{block}/toggle', [ContentBlockController::class, 'toggle'])->name('content.blocks.toggle');
        Route::delete('content/{content}/blocks/{block}', [ContentBlockController::class, 'destroy'])->name('content.blocks.destroy');
        Route::post('content/{content}/blocks/reorder', [ContentBlockController::class, 'reorder'])->name('content.blocks.reorder');

        Route::get('pages', [PageController::class, 'index'])->name('pages.index');
        Route::get('pages/create', [PageController::class, 'create'])->name('pages.create');
        Route::post('pages', [PageController::class, 'store'])->name('pages.store');
        Route::get('pages/{page}/edit', [PageController::class, 'edit'])->name('pages.edit');
        Route::put('pages/{page}', [PageController::class, 'update'])->name('pages.update');
        Route::get('pages/{page}/preview', [PageController::class, 'preview'])->name('pages.preview');
        Route::post('pages/{page}/duplicate', [PageController::class, 'duplicate'])->name('pages.duplicate');
        Route::post('pages/{page}/submit', [PageController::class, 'submit'])->name('pages.submit');
        Route::post('pages/{page}/approve', [PageController::class, 'approve'])->name('pages.approve');
        Route::post('pages/{page}/reject', [PageController::class, 'reject'])->name('pages.reject');
        Route::post('pages/{page}/publish', [PageController::class, 'publish'])->name('pages.publish');
        Route::post('pages/{page}/unpublish', [PageController::class, 'unpublish'])->name('pages.unpublish');
        Route::post('pages/{page}/archive', [PageController::class, 'archive'])->name('pages.archive');
        Route::delete('pages/{page}', [PageController::class, 'destroy'])->name('pages.destroy');
        Route::post('pages/{page}/restore', [PageController::class, 'restore'])->name('pages.restore')->withTrashed();
        Route::delete('pages/{page}/force-delete', [PageController::class, 'forceDestroy'])->name('pages.force-delete')->withTrashed();

        Route::post('pages/{page}/blocks', [PageBlockController::class, 'store'])->name('pages.blocks.store');
        Route::put('pages/{page}/blocks/{block}', [PageBlockController::class, 'update'])->name('pages.blocks.update');
        Route::post('pages/{page}/blocks/{block}/duplicate', [PageBlockController::class, 'duplicate'])->name('pages.blocks.duplicate');
        Route::post('pages/{page}/blocks/{block}/toggle', [PageBlockController::class, 'toggle'])->name('pages.blocks.toggle');
        Route::delete('pages/{page}/blocks/{block}', [PageBlockController::class, 'destroy'])->name('pages.blocks.destroy');
        Route::post('pages/{page}/blocks/reorder', [PageBlockController::class, 'reorder'])->name('pages.blocks.reorder');

        Route::get('media', [MediaController::class, 'index'])->name('media.index');
        Route::get('media/picker', [MediaController::class, 'picker'])->name('media.picker');
        Route::get('media/trash', [MediaController::class, 'trash'])->name('media.trash');
        Route::post('media/empty-trash', [MediaController::class, 'emptyTrash'])->name('media.empty-trash');
        Route::post('media', [MediaController::class, 'store'])->name('media.store');
        Route::put('media/{medium}', [MediaController::class, 'update'])->name('media.update');
        Route::post('media/{medium}/rename', [MediaController::class, 'rename'])->name('media.rename');
        Route::post('media/{medium}/duplicate', [MediaController::class, 'duplicate'])->name('media.duplicate');
        Route::post('media/{medium}/replace', [MediaController::class, 'replace'])->name('media.replace');
        Route::post('media/{medium}/move', [MediaController::class, 'move'])->name('media.move');
        Route::get('media/{medium}/references', [MediaController::class, 'references'])->name('media.references');
        Route::delete('media/{medium}', [MediaController::class, 'destroy'])->name('media.destroy');
        Route::post('media/{medium}/restore', [MediaController::class, 'restore'])->name('media.restore')->withTrashed();
        Route::delete('media/{medium}/force-delete', [MediaController::class, 'forceDestroy'])->name('media.force-delete')->withTrashed();
        Route::post('media/move-item', [MediaController::class, 'moveItem'])->name('media.move-item');
        Route::post('media/bulk-action', [MediaController::class, 'bulkAction'])->name('media.bulk-action');

        Route::post('media/folders', [MediaFolderController::class, 'store'])->name('media.folders.store');
        Route::put('media/folders/{folder}', [MediaFolderController::class, 'update'])->name('media.folders.update');
        Route::post('media/folders/{folder}/move', [MediaFolderController::class, 'move'])->name('media.folders.move');
        Route::post('media/folders/{folder}/duplicate', [MediaFolderController::class, 'duplicate'])->name('media.folders.duplicate');
        Route::get('media/folders/{folder}/properties', [MediaFolderController::class, 'properties'])->name('media.folders.properties');
        Route::delete('media/folders/{folder}', [MediaFolderController::class, 'destroy'])->name('media.folders.destroy');
        Route::post('media/folders/{folder}/restore', [MediaFolderController::class, 'restore'])->name('media.folders.restore')->withTrashed();
        Route::delete('media/folders/{folder}/force-delete', [MediaFolderController::class, 'forceDestroy'])->name('media.folders.force-delete')->withTrashed();

        Route::resource('users', UserController::class)->except(['show']);

        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');

        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

        Route::get('audit-logs', AuditLogController::class)->name('audit-logs.index');
    });
});
