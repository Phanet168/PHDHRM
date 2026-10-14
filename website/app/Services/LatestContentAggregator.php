<?php

namespace App\Services;

use App\Models\HealthContent;
use App\Models\LegalDocument;
use App\Models\News;
use App\Support\LatestContentItem;
use Illuminate\Support\Collection;

class LatestContentAggregator
{
    /**
     * Newest published content across News, Health Education and Legal
     * Documents, normalized into a single sorted list for the homepage's
     * mixed-content slider. No new table — this only reads existing models.
     */
    public function latest(int $limit = 10): Collection
    {
        $news = News::published()->with(['category', 'featuredMedia'])
            ->latest('published_at')->latest('id')->take($limit)->get()
            ->map(fn (News $article) => new LatestContentItem(
                type: 'news',
                title: $article->title_km,
                thumbnail: $article->featuredImageUrl(),
                categoryLabel: $article->category?->name_km ?? 'ព័ត៌មាន',
                publishedAt: $article->published_at,
                url: route('news.show', $article),
            ));

        $health = HealthContent::published()->with('category')
            ->latest('published_at')->latest('id')->take($limit)->get()
            ->map(fn (HealthContent $article) => new LatestContentItem(
                type: 'health_education',
                title: $article->title_km,
                thumbnail: $article->featured_image,
                categoryLabel: $article->category?->name_km ?? 'អប់រំសុខភាព',
                publishedAt: $article->published_at,
                url: route('health-content.show', $article),
            ));

        $legal = LegalDocument::published()
            ->latest('published_at')->latest('id')->take($limit)->get()
            ->map(fn (LegalDocument $document) => new LatestContentItem(
                type: 'legal_document',
                title: $document->title,
                thumbnail: null,
                categoryLabel: 'លិខិតបទដ្ឋានគតិយុត្ត',
                publishedAt: $document->published_at ?? $document->issue_date ?? $document->created_at,
                url: route('legal-documents.download', $document),
            ));

        return $news->concat($health)->concat($legal)
            ->sortByDesc(fn (LatestContentItem $item) => $item->publishedAt)
            ->take($limit)
            ->values();
    }
}
