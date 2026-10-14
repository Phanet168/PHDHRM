<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageBlock;
use App\Services\PageBlockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PageBlockController extends Controller
{
    public function __construct(private PageBlockService $blocks)
    {
    }

    public function store(Request $request, Page $page): RedirectResponse
    {
        $this->authorize('update', $page);

        $blockType = $request->input('block_type');
        $request->validate(['block_type' => ['required', Rule::in(PageBlockService::BLOCK_TYPES)]]);

        if ($blockType === 'html_css') {
            $this->authorize('useCustomHtml', Page::class);
        }

        $this->blocks->create($page, $blockType, $request->input('data', []), $request);

        return back()->with('status', 'បានបន្ថែម Block ដោយជោគជ័យ។');
    }

    public function update(Request $request, Page $page, PageBlock $block): RedirectResponse
    {
        $this->authorize('update', $page);
        abort_unless($block->page_id === $page->id, 404);

        if ($block->block_type === 'html_css') {
            $this->authorize('useCustomHtml', Page::class);
        }

        $this->blocks->update($block, $request->input('data', []), $request);

        return back()->with('status', 'បានធ្វើបច្ចុប្បន្នភាព Block ដោយជោគជ័យ។');
    }

    public function destroy(Page $page, PageBlock $block): RedirectResponse
    {
        $this->authorize('update', $page);
        abort_unless($block->page_id === $page->id, 404);

        $this->blocks->delete($block);

        return back()->with('status', 'បានលុប Block ដោយជោគជ័យ។');
    }

    public function duplicate(Page $page, PageBlock $block): RedirectResponse
    {
        $this->authorize('update', $page);
        abort_unless($block->page_id === $page->id, 404);

        if ($block->block_type === 'html_css') {
            $this->authorize('useCustomHtml', Page::class);
        }

        $this->blocks->duplicate($block);

        return back()->with('status', 'បានចម្លង Block ដោយជោគជ័យ។');
    }

    public function toggle(Page $page, PageBlock $block): RedirectResponse
    {
        $this->authorize('update', $page);
        abort_unless($block->page_id === $page->id, 404);

        $this->blocks->toggle($block);

        return back()->with('status', 'បានផ្លាស់ប្តូរស្ថានភាព Block ដោយជោគជ័យ។');
    }

    public function reorder(Request $request, Page $page): JsonResponse
    {
        $this->authorize('update', $page);

        $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:page_blocks,id'],
            'items.*.sort_order' => ['required', 'integer'],
        ]);

        $this->blocks->reorder($page, $request->input('items'));

        return response()->json(['status' => 'ok']);
    }
}
