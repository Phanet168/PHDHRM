<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MenuItemRequest;
use App\Models\AuditLog;
use App\Models\Content;
use App\Models\ContentCategory;
use App\Models\Facility;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\NewsCategory;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MenuItemController extends Controller
{
    public function create(Menu $menu): View
    {
        $this->authorize('update', $menu);

        return view('admin.menus.items.create', [
            'menu' => $menu,
            'parents' => $menu->topLevelItems()->get(),
            ...$this->linkableOptions(),
        ]);
    }

    public function store(MenuItemRequest $request, Menu $menu): RedirectResponse
    {
        $data = $request->validated();
        $data['menu_id'] = $menu->id;
        $data['is_active'] = $request->boolean('is_active');
        $data['linkable_type'] = $this->linkableType($request->input('link_type'));
        $data['linkable_id'] = $data['linkable_type'] ? $data['linkable_id'] : null;
        $data['sort_order'] = $menu->items()->where('parent_id', $data['parent_id'] ?? null)->count();

        $item = MenuItem::create($data);

        AuditLog::record('created', 'MenuItem', $item->id, "បង្កើតធាតុម៉ឺនុយ៖ {$item->label_km}");

        return redirect()->route('admin.menus.edit', $menu)->with('status', 'បានបន្ថែមធាតុម៉ឺនុយដោយជោគជ័យ។');
    }

    public function edit(Menu $menu, MenuItem $item): View
    {
        $this->authorize('update', $menu);
        abort_unless($item->menu_id === $menu->id, 404);

        return view('admin.menus.items.edit', [
            'menu' => $menu,
            'item' => $item,
            'parents' => $menu->topLevelItems()->where('id', '!=', $item->id)->get(),
            ...$this->linkableOptions(),
        ]);
    }

    public function update(MenuItemRequest $request, Menu $menu, MenuItem $item): RedirectResponse
    {
        abort_unless($item->menu_id === $menu->id, 404);

        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $data['linkable_type'] = $this->linkableType($request->input('link_type'));
        $data['linkable_id'] = $data['linkable_type'] ? $data['linkable_id'] : null;

        $item->update($data);

        AuditLog::record('updated', 'MenuItem', $item->id, "កែប្រែធាតុម៉ឺនុយ៖ {$item->label_km}");

        return redirect()->route('admin.menus.edit', $menu)->with('status', 'បានរក្សាទុកដោយជោគជ័យ។');
    }

    public function destroy(Menu $menu, MenuItem $item): RedirectResponse
    {
        $this->authorize('update', $menu);
        abort_unless($item->menu_id === $menu->id, 404);

        $item->delete();

        AuditLog::record('deleted', 'MenuItem', $item->id, "លុបធាតុម៉ឺនុយ៖ {$item->label_km}");

        return redirect()->route('admin.menus.edit', $menu)->with('status', 'បានលុបធាតុម៉ឺនុយដោយជោគជ័យ។');
    }

    public function toggle(Menu $menu, MenuItem $item): RedirectResponse
    {
        $this->authorize('update', $menu);
        abort_unless($item->menu_id === $menu->id, 404);

        $item->update(['is_active' => ! $item->is_active]);

        return back()->with('status', 'បានធ្វើបច្ចុប្បន្នភាពស្ថានភាពដោយជោគជ័យ។');
    }

    public function reorder(Request $request, Menu $menu): JsonResponse
    {
        $this->authorize('update', $menu);

        $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.parent_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'items.*.sort_order' => ['required', 'integer'],
        ]);

        foreach ($request->input('items') as $row) {
            $item = MenuItem::find($row['id']);
            if ($item && $item->menu_id === $menu->id) {
                $item->update([
                    'parent_id' => $row['parent_id'] ?: null,
                    'sort_order' => $row['sort_order'],
                ]);
            }
        }

        AuditLog::record('updated', 'Menu', $menu->id, "តម្រៀបធាតុម៉ឺនុយឡើងវិញ៖ {$menu->name}");

        return response()->json(['status' => 'ok']);
    }

    private function linkableType(?string $linkType): ?string
    {
        return match ($linkType) {
            'page' => Page::class,
            'facility' => Facility::class,
            'news_category' => NewsCategory::class,
            'content_category' => ContentCategory::class,
            'content' => Content::class,
            default => null,
        };
    }

    private function linkableOptions(): array
    {
        return [
            'pages' => Page::orderBy('title_km')->get(),
            'facilities' => Facility::orderBy('name_km')->get(),
            'newsCategories' => NewsCategory::orderBy('name_km')->get(),
            'contentCategories' => ContentCategory::orderBy('name_km')->get(),
            'contents' => Content::where('status', 'published')->orderBy('title_km')->get(),
        ];
    }
}
