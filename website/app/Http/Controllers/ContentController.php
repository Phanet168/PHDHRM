<?php

namespace App\Http\Controllers;

use App\Models\Content;
use App\Models\ContentCategory;
use App\Models\News;
use App\Services\VisitorStatistics;

class ContentController extends Controller
{
    public function show(Content $content, VisitorStatistics $statistics)
    {
        $canPreview = auth()->check() && auth()->user()->can('content.view');
        abort_unless($content->status === 'published' || $canPreview, 404);

        $content->load(['category', 'author', 'images' => fn ($q) => $q->orderBy('sort_order'), 'blocks' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')]);

        if ($content->status === 'published' && ! $canPreview) {
            $content->increment('views_count');
        }

        $related = Content::published()
            ->when($content->category_id, fn ($q) => $q->where('category_id', $content->category_id))
            ->where('id', '!=', $content->id)
            ->latest('published_at')
            ->take(4)
            ->get();

        $previous = Content::published()
            ->where('published_at', '<', $content->published_at)
            ->orderByDesc('published_at')
            ->first();

        $next = Content::published()
            ->where('published_at', '>', $content->published_at)
            ->orderBy('published_at')
            ->first();

        $categories = ContentCategory::withCount(['contents' => fn ($q) => $q->published()])
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name_km')->get();

        $latestNews = News::published()->latest('published_at')->take(5)->get();

        $visitorStats = $statistics->summary();

        return view('content.show', [
            'article' => $content,
            'related' => $related,
            'previous' => $previous,
            'next' => $next,
            'categories' => $categories,
            'latestNews' => $latestNews,
            'visitorStats' => $visitorStats,
            'visitorCountries' => $statistics->countries(),
        ]);
    }
}
