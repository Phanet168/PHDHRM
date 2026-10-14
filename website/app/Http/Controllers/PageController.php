<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\PageRendererService;

class PageController extends Controller
{
    public function show(Page $page, PageRendererService $renderer)
    {
        abort_unless($page->status === 'published', 404);

        $page = $renderer->forShow($page);

        return view('pages.show', ['page' => $page, 'isPreview' => false]);
    }
}
