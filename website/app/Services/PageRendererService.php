<?php

namespace App\Services;

use App\Models\Page;

class PageRendererService
{
    /**
     * Loads a page for public rendering: only its active blocks, in order,
     * with the featured media and author eager-loaded for the template/SEO.
     */
    public function forShow(Page $page): Page
    {
        $page->load([
            'blocks' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
            'featuredMedia',
            'author',
        ]);

        return $page;
    }
}
