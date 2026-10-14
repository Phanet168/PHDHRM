<?php

namespace App\Http\Controllers;

use App\Models\HealthContent;
use App\Models\HeroSlide;
use App\Models\HomepageQuickLink;
use App\Models\LeaderRecommendation;
use App\Models\LegalDocument;
use App\Models\News;
use App\Models\Partner;
use App\Services\LatestContentAggregator;

class HomeController extends Controller
{
    public function index(LatestContentAggregator $latestContentAggregator)
    {
        $heroSlides = HeroSlide::active()->orderBy('sort_order')->get();

        $leaderRecommendation = LeaderRecommendation::published()
            ->with(['media', 'activeItems'])
            ->latest('published_at')
            ->first();

        // Featured: prefer an article explicitly marked is_featured, otherwise
        // fall back to the newest published article. The right-side list
        // always excludes whichever article ends up featured.
        $featuredNews = News::published()->with(['category', 'featuredMedia'])->where('is_featured', true)
            ->latest('published_at')->latest('id')->first()
            ?? News::published()->with(['category', 'featuredMedia'])->latest('published_at')->latest('id')->first();

        $secondaryNews = $featuredNews
            ? News::published()->with(['category', 'featuredMedia'])->where('id', '!=', $featuredNews->id)
                ->latest('published_at')->latest('id')->take(3)->get()
            : collect();

        $latestContent = $latestContentAggregator->latest(10);

        $quickLinks = HomepageQuickLink::active()->with('media')->orderBy('sort_order')->get();

        // Featured: prefer a video explicitly marked is_featured, otherwise
        // fall back to the newest published video. The right-side list
        // always excludes whichever video ends up featured. Plain
        // text-only HealthContent articles (no video attached) never
        // appear here — this section is video-only.
        $featuredHealthVideo = HealthContent::published()->withVideo()->with('category')->where('is_featured', true)
            ->latest('published_at')->latest('id')->first()
            ?? HealthContent::published()->withVideo()->with('category')->latest('published_at')->latest('id')->first();

        $secondaryHealthVideos = $featuredHealthVideo
            ? HealthContent::published()->withVideo()->with('category')->where('id', '!=', $featuredHealthVideo->id)
                ->latest('published_at')->latest('id')->take(3)->get()
            : collect();

        $legalDocuments = LegalDocument::published()->latest('issue_date')->latest('id')->take(3)->get();
        $partners = Partner::active()->orderBy('sort_order')->get();

        $tickerNews = News::published()->latest('published_at')->take(8)->get();
        $tickerTotalChars = $tickerNews->sum(fn ($article) => mb_strlen($article->title_km));
        $tickerDuration = (int) min(40, max(25, 25 + $tickerTotalChars / 12));

        return view('home', compact(
            'heroSlides', 'leaderRecommendation', 'featuredNews', 'secondaryNews', 'latestContent', 'quickLinks',
            'featuredHealthVideo', 'secondaryHealthVideos', 'legalDocuments', 'partners', 'tickerNews', 'tickerDuration'
        ));
    }
}
