<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContentCategoryRequest;
use App\Models\AuditLog;
use App\Models\ContentCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContentCategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', ContentCategory::class);

        $categories = ContentCategory::with('parent')->withCount('contents')
            ->orderBy('sort_order')->orderBy('name_km')->get();

        return view('admin.content-categories.index', compact('categories'));
    }

    public function create(): View
    {
        $this->authorize('create', ContentCategory::class);

        $parents = ContentCategory::orderBy('name_km')->get();

        return view('admin.content-categories.create', compact('parents'));
    }

    public function store(ContentCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        $category = ContentCategory::create($data);

        AuditLog::record('created', 'ContentCategory', $category->id, "បង្កើតប្រភេទមាតិកា៖ {$category->name_km}");

        return redirect()->route('admin.content-categories.index')->with('status', 'បានបង្កើតប្រភេទមាតិកាដោយជោគជ័យ។');
    }

    public function edit(ContentCategory $contentCategory): View
    {
        $this->authorize('update', $contentCategory);

        $parents = ContentCategory::where('id', '!=', $contentCategory->id)->orderBy('name_km')->get();

        return view('admin.content-categories.edit', ['category' => $contentCategory, 'parents' => $parents]);
    }

    public function update(ContentCategoryRequest $request, ContentCategory $contentCategory): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $contentCategory->update($data);

        AuditLog::record('updated', 'ContentCategory', $contentCategory->id, "កែប្រែប្រភេទមាតិកា៖ {$contentCategory->name_km}");

        return redirect()->route('admin.content-categories.index')->with('status', 'បានរក្សាទុកដោយជោគជ័យ។');
    }

    public function destroy(ContentCategory $contentCategory): RedirectResponse
    {
        $this->authorize('delete', $contentCategory);

        $contentCategory->delete();

        AuditLog::record('deleted', 'ContentCategory', $contentCategory->id, "លុបប្រភេទមាតិកា៖ {$contentCategory->name_km}");

        return redirect()->route('admin.content-categories.index')->with('status', 'បានលុបប្រភេទមាតិកាដោយជោគជ័យ។');
    }
}
