<?php

namespace App\Http\Controllers;

use App\Models\ContentCategory;

class CategoryController extends Controller
{
    public function show(ContentCategory $category)
    {
        abort_unless($category->is_active, 404);

        $contents = $category->contents()
            ->published()
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        return view('content.category', compact('category', 'contents'));
    }
}
