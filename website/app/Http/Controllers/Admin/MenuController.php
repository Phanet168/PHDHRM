<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MenuRequest;
use App\Models\AuditLog;
use App\Models\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Menu::class);

        $menus = Menu::withCount('items')->orderBy('name')->get();

        return view('admin.menus.index', compact('menus'));
    }

    public function create(): View
    {
        $this->authorize('create', Menu::class);

        return view('admin.menus.create');
    }

    public function store(MenuRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $menu = Menu::create($data);

        AuditLog::record('created', 'Menu', $menu->id, "បង្កើតម៉ឺនុយ៖ {$menu->name}");

        return redirect()->route('admin.menus.edit', $menu)->with('status', 'បានបង្កើតម៉ឺនុយដោយជោគជ័យ។');
    }

    public function edit(Menu $menu): View
    {
        $this->authorize('update', $menu);

        $items = $menu->items()->with('linkable')->get();

        // Preview always reflects the current items (including disabled ones, shown dimmed)
        // regardless of the menu's published state, so admins can review before publishing.
        $tree = $this->buildPreviewTree($items);

        return view('admin.menus.edit', compact('menu', 'items', 'tree'));
    }

    public function update(MenuRequest $request, Menu $menu): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $menu->update($data);

        AuditLog::record('updated', 'Menu', $menu->id, "កែប្រែម៉ឺនុយ៖ {$menu->name}");

        return redirect()->route('admin.menus.edit', $menu)->with('status', 'បានរក្សាទុកដោយជោគជ័យ។');
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        $this->authorize('delete', $menu);

        $menu->delete();

        AuditLog::record('deleted', 'Menu', $menu->id, "លុបម៉ឺនុយ៖ {$menu->name}");

        return redirect()->route('admin.menus.index')->with('status', 'បានលុបម៉ឺនុយដោយជោគជ័យ។');
    }

    private function buildPreviewTree($items)
    {
        return $items->where('parent_id', null)->sortBy('sort_order')->map(function ($item) use ($items) {
            $item->setRelation('children', $items->where('parent_id', $item->id)->sortBy('sort_order')->values());

            return $item;
        })->values();
    }
}
