<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Models\NewsCategory;
use App\Services\VisitorStatistics;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function index(Request $request, VisitorStatistics $statistics)
    {
        $request->validate(['category' => ['nullable', 'string', 'max:255']]);
        $query = News::published()->with(['category', 'featuredMedia']);

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->input('category')));
        }

        // 9 per page = three rows of the 3-column desktop grid.
        $articles = $query->latest('published_at')->latest('id')->paginate(9)->withQueryString();
        $featuredArticles = News::published()->with(['category', 'featuredMedia'])
            ->orderByDesc('is_featured')->latest('published_at')->latest('id')->limit(5)->get();
        $categories = NewsCategory::withCount(['news' => fn ($query) => $query->published()])
            ->orderBy('sort_order')->orderBy('id')->get();
        $visitorStats = $statistics->summary();
        $visitorCountries = $statistics->countries();
        $publishedCount = News::published()->count();

        return view('news.index', compact('articles', 'categories', 'featuredArticles', 'visitorStats', 'visitorCountries', 'publishedCount'));
    }

    public function show(News $news, VisitorStatistics $statistics)
    {
        abort_unless($news->status === 'published' && $news->published_at?->isPast(), 404);

        $news->load(['category', 'author', 'featuredMedia', 'images.media']);
        $news->increment('views_count');

        $related = News::published()->with('featuredMedia')
            ->when($news->category_id, fn ($q) => $q->where('category_id', $news->category_id))
            ->where('id', '!=', $news->id)
            ->latest('published_at')
            ->take(4)
            ->get();

        $previous = News::published()->with('featuredMedia')
            ->where('published_at', '<', $news->published_at)
            ->orderByDesc('published_at')
            ->first();

        $next = News::published()->with('featuredMedia')
            ->where('published_at', '>', $news->published_at)
            ->orderBy('published_at')
            ->first();

        $categories = NewsCategory::withCount(['news' => fn ($q) => $q->published()])
            ->orderBy('sort_order')->orderBy('id')->get();

        $latestNews = News::published()->with('featuredMedia')
            ->where('id', '!=', $news->id)
            ->latest('published_at')
            ->take(5)
            ->get();

        $visitorStats = $statistics->summary();
        $publishedCount = News::published()->count();

        return view('news.show', [
            'article' => $news,
            'related' => $related,
            'previous' => $previous,
            'next' => $next,
            'categories' => $categories,
            'latestNews' => $latestNews,
            'visitorStats' => $visitorStats,
            'visitorCountries' => $statistics->countries(),
            'publishedCount' => $publishedCount,
        ]);
    }
}
