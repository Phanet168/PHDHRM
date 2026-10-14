<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\Menu;
use App\Models\Page;
use App\Services\MediaManagerService;
use App\Services\MediaUploader;
use App\Services\PageMenuSyncService;
use App\Services\PageRendererService;
use App\Services\PageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PageController extends Controller
{
    public function __construct(private PageService $pages, private PageMenuSyncService $menuSync)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Page::class);

        $query = Page::with(['author', 'menuItems.menu']);

        if ($request->input('status', 'all') === 'trash') {
            $query = Page::onlyTrashed()->with(['author', 'menuItems.menu']);
        } elseif ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title_km', 'like', "%{$search}%")
                    ->orWhere('title_en', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $pages = $query->latest('updated_at')->paginate(15)->withQueryString();

        return view('admin.pages.index', compact('pages'));
    }

    public function create(): View
    {
        $this->authorize('create', Page::class);

        return view('admin.pages.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Page::class);

        $data = $request->validate([
            'title_km' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'template' => ['required', 'in:default,about,contact,leadership,full_width,landing'],
        ]);

        $data['slug'] = $this->pages->uniqueSlug(($data['slug'] ?? null) ?: ($data['title_en'] ?? null) ?: $data['title_km']);
        $data['status'] = 'draft';
        $data['author_id'] = auth()->id();
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $page = Page::create($data);

        AuditLog::record('created', 'Page', $page->id, "បង្កើតទំព័រ៖ {$page->title_km}");

        return redirect()->route('admin.pages.edit', $page)->with('status', 'បានបង្កើតទំព័រដោយជោគជ័យ។');
    }

    public function edit(Page $page): View
    {
        $this->authorize('update', $page);

        $page->load(['blocks' => fn ($q) => $q->orderBy('sort_order'), 'featuredMedia', 'reviewLogs.reviewer']);

        $headerItem = $page->menuItems()->whereHas('menu', fn ($q) => $q->where('slug', 'header'))->first();
        $footerItem = $page->menuItems()->whereHas('menu', fn ($q) => $q->where('slug', 'footer'))->first();
        $headerParents = Menu::where('slug', 'header')->first()?->topLevelItems()->get() ?? collect();
        $footerParents = Menu::where('slug', 'footer')->first()?->topLevelItems()->get() ?? collect();
        $pageFolderId = app(MediaManagerService::class)->resolveFolderForRecord('pages', $page, "#{$page->id} — {$page->title_km}");

        return view('admin.pages.edit', compact('page', 'headerItem', 'footerItem', 'headerParents', 'footerParents', 'pageFolderId'));
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $this->authorize('update', $page);

        $data = $request->validate([
            'title_km' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'template' => ['required', 'in:default,about,contact,leadership,full_width,landing'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'string', 'max:255'],
        ]);

        $data['slug'] = $this->pages->uniqueSlug($data['slug'], $page->id);
        $data['updated_by'] = auth()->id();

        if ($request->hasFile('featured_image')) {
            $label = '#'.$page->id.' — '.($data['title_km'] ?? $page->title_km);
            $folderId = app(MediaManagerService::class)->resolveFolderForRecord('pages', $page, $label);
            $path = MediaUploader::store($request->file('featured_image'), 'pages', MediaUploader::IMAGE_ONLY_EXTENSIONS, MediaUploader::IMAGE_ONLY_MIMES, $folderId);
            $media = Media::where('disk_path', $path)->first();
            $data['featured_media_id'] = $media?->id;
        } elseif ($request->filled('featured_media_id')) {
            $data['featured_media_id'] = $request->input('featured_media_id');
        } elseif ($request->boolean('remove_featured_image')) {
            $data['featured_media_id'] = null;
        }

        $page->update($data);

        if ($this->authorizeMenu($page)) {
            $this->menuSync->sync($page, [
                'header' => [
                    'enabled' => $request->boolean('menu_header_enabled'),
                    'label' => $request->input('menu_header_label'),
                    'parent_id' => $request->input('menu_header_parent_id'),
                    'sort_order' => (int) $request->input('menu_header_sort_order', 0),
                    'target' => $request->input('menu_header_target', '_self'),
                    'sync_label' => $request->boolean('menu_header_sync_label'),
                ],
                'footer' => [
                    'enabled' => $request->boolean('menu_footer_enabled'),
                    'label' => $request->input('menu_footer_label'),
                    'parent_id' => $request->input('menu_footer_parent_id'),
                    'sort_order' => (int) $request->input('menu_footer_sort_order', 0),
                    'target' => $request->input('menu_footer_target', '_self'),
                    'sync_label' => $request->boolean('menu_footer_sync_label'),
                ],
            ]);
        }

        AuditLog::record('updated', 'Page', $page->id, "កែប្រែទំព័រ៖ {$page->title_km}");

        return redirect()->route('admin.pages.edit', $page)->with('status', 'បានរក្សាទុកដោយជោគជ័យ។');
    }

    public function preview(Page $page, PageRendererService $renderer): View
    {
        $this->authorize('view', $page);

        $page = $renderer->forShow($page);

        return view('pages.show', ['page' => $page, 'isPreview' => true]);
    }

    public function duplicate(Page $page): RedirectResponse
    {
        $this->authorize('create', Page::class);
        $this->authorize('update', $page);

        $copy = $this->pages->duplicate($page);

        return redirect()->route('admin.pages.edit', $copy)->with('status', 'បានចម្លងទំព័រដោយជោគជ័យ។');
    }

    public function submit(Page $page): RedirectResponse
    {
        $this->authorize('submitReview', $page);

        $this->pages->submitForReview($page);

        return back()->with('status', 'បានដាក់ស្នើសម្រាប់ការត្រួតពិនិត្យ។');
    }

    public function approve(Request $request, Page $page): RedirectResponse
    {
        $this->authorize('approve', $page);

        $this->pages->approve($page, $request->input('note'));

        return back()->with('status', 'បានអនុម័តទំព័រដោយជោគជ័យ។');
    }

    public function reject(Request $request, Page $page): RedirectResponse
    {
        $this->authorize('review', $page);

        $request->validate(['note' => ['required', 'string', 'max:1000']]);

        $this->pages->reject($page, $request->input('note'));

        return back()->with('status', 'បានច្រានចោលទំព័រ ហើយបានផ្ញើមតិមកអ្នកនិពន្ធ។');
    }

    public function publish(Page $page): RedirectResponse
    {
        $this->authorize('publish', $page);

        $this->pages->publish($page);

        return back()->with('status', 'បានផ្សាយទំព័រដោយជោគជ័យ។');
    }

    public function unpublish(Page $page): RedirectResponse
    {
        $this->authorize('unpublish', $page);

        $this->pages->unpublish($page);

        return back()->with('status', 'បានដកទំព័រពីការផ្សាយ។');
    }

    public function archive(Page $page): RedirectResponse
    {
        $this->authorize('unpublish', $page);

        $this->pages->archive($page);

        return back()->with('status', 'បានទុកទំព័រក្នុងឯកសារ។');
    }

    public function destroy(Page $page): RedirectResponse
    {
        $this->authorize('delete', $page);

        $this->pages->trash($page);

        return redirect()->route('admin.pages.index')->with('status', 'បានទុកទំព័រក្នុងធុងសម្រាមដោយជោគជ័យ។');
    }

    public function restore(Page $page): RedirectResponse
    {
        $this->authorize('restore', $page);

        $this->pages->restore($page);

        return redirect()->route('admin.pages.index')->with('status', 'បានសង្គ្រោះទំព័រដោយជោគជ័យ។');
    }

    public function forceDestroy(Page $page): RedirectResponse
    {
        $this->authorize('forceDelete', $page);

        try {
            $this->pages->forceDelete($page);
        } catch (RuntimeException $e) {
            return back()->withErrors(['page' => $e->getMessage()]);
        }

        return redirect()->route('admin.pages.index')->with('status', 'បានលុបទំព័រជាអចិន្ត្រៃយ៍ដោយជោគជ័យ។');
    }

    private function authorizeMenu(Page $page): bool
    {
        return auth()->user()->can('manageMenu', $page);
    }
}
