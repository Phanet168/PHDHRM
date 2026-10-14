<?php

namespace App\Http\Controllers;

use App\Models\HealthContent;
use App\Models\HealthContentCategory;
use Illuminate\Http\Request;

class HealthContentController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['category' => ['nullable', 'string', 'max:255']]);

        $query = HealthContent::published()->with('category');

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->input('category')));
        }

        $articles = $query->latest('published_at')->latest('id')->paginate(9)->withQueryString();
        $categories = HealthContentCategory::withCount(['contents' => fn ($q) => $q->published()])
            ->orderBy('sort_order')->orderBy('id')->get();
        $activeCategory = $request->filled('category')
            ? $categories->firstWhere('slug', $request->input('category'))
            : null;
        $publishedCount = HealthContent::published()->count();

        return view('health-content.index', compact('articles', 'categories', 'activeCategory', 'publishedCount'));
    }

    public function show(HealthContent $healthContent)
    {
        abort_unless($healthContent->status === 'published', 404);

        $healthContent->load('category');

        $related = HealthContent::published()
            ->when($healthContent->category_id, fn ($q) => $q->where('category_id', $healthContent->category_id))
            ->where('id', '!=', $healthContent->id)
            ->latest('published_at')
            ->take(4)
            ->get();

        $categories = HealthContentCategory::withCount(['contents' => fn ($q) => $q->published()])
            ->orderBy('sort_order')->orderBy('id')->get();

        return view('health-content.show', [
            'article' => $healthContent,
            'related' => $related,
            'categories' => $categories,
        ]);
    }
}
