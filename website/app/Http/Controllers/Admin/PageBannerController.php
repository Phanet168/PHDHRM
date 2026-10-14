<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\PageBanner;
use App\Services\MediaManagerService;
use App\Services\MediaUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageBannerController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', PageBanner::class);

        $banners = PageBanner::with('media')->get()->keyBy('page_key');

        return view('admin.page-banners.index', ['pages' => PageBanner::PAGES, 'banners' => $banners]);
    }

    public function edit(string $pageKey): View
    {
        $banner = $this->bannerFor($pageKey);
        $this->authorize('update', $banner);

        $folderId = app(MediaManagerService::class)->resolveFolderForRecord(
            'page-banners', $banner, '#'.$banner->id.' — '.PageBanner::label($pageKey)
        );

        return view('admin.page-banners.edit', [
            'pageKey' => $pageKey,
            'banner' => $banner->load('media'),
            'currentUrl' => PageBanner::urlFor($pageKey),
            'folderId' => $folderId,
        ]);
    }

    public function update(Request $request, string $pageKey): RedirectResponse
    {
        $banner = $this->bannerFor($pageKey);
        $this->authorize('update', $banner);

        $request->validate([
            'media_id' => ['nullable', 'integer'],
            'banner_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'reset_to_default' => ['nullable', 'boolean'],
        ]);

        if ($request->hasFile('banner_file')) {
            $folderId = app(MediaManagerService::class)->resolveFolderForRecord(
                'page-banners', $banner, '#'.$banner->id.' — '.PageBanner::label($pageKey)
            );
            $path = MediaUploader::store(
                $request->file('banner_file'), 'page-banners',
                MediaUploader::IMAGE_ONLY_EXTENSIONS, MediaUploader::IMAGE_ONLY_MIMES, $folderId
            );
            $banner->update(['media_id' => Media::where('disk_path', $path)->value('id')]);
        } elseif ($request->boolean('reset_to_default')) {
            $banner->update(['media_id' => null]);
        } elseif ($request->filled('media_id')) {
            $media = Media::find($request->input('media_id'));
            abort_unless($media && $media->isImage(), 422, 'ឯកសារដែលបានជ្រើសរើសមិនមែនជារូបភាពទេ។');
            $banner->update(['media_id' => $media->id]);
        }

        AuditLog::record('updated', 'PageBanner', $banner->id, 'កែប្រែផ្ទាំងបដាទំព័រ៖ '.PageBanner::label($pageKey));

        return redirect()->route('admin.page-banners.edit', $pageKey)->with('status', 'បានរក្សាទុកដោយជោគជ័យ។');
    }

    private function bannerFor(string $pageKey): PageBanner
    {
        abort_unless(PageBanner::isKnown($pageKey), 404);

        return PageBanner::firstOrCreate(['page_key' => $pageKey]);
    }
}
