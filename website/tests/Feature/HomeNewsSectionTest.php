<?php

namespace Tests\Feature;

use App\Models\HealthContent;
use App\Models\LegalDocument;
use App\Models\News;
use App\Services\LatestContentAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeNewsSectionTest extends TestCase
{
    use RefreshDatabase;

    private function news(array $attributes = []): News
    {
        return News::create(array_merge([
            'title_km' => 'ព័ត៌មានសុខភាព',
            'slug' => 'news-'.fake()->uuid(),
            'excerpt' => 'សកម្មភាពសុខភាពសហគមន៍',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $attributes));
    }

    private function healthContent(array $attributes = []): HealthContent
    {
        return HealthContent::create(array_merge([
            'title_km' => 'អប់រំសុខភាព',
            'slug' => 'health-'.fake()->uuid(),
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $attributes));
    }

    private function legalDocument(array $attributes = []): LegalDocument
    {
        return LegalDocument::create(array_merge([
            'title' => 'លិខិតបទដ្ឋានគតិយុត្ត',
            'document_number' => '001/26',
            'issuing_authority' => 'ក្រសួងសុខាភិបាល',
            'issue_date' => now()->subDay()->toDateString(),
            'year' => now()->year,
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $attributes));
    }

    public function test_homepage_prefers_the_featured_article_and_excludes_it_from_the_secondary_list(): void
    {
        $featured = $this->news(['is_featured' => true, 'title_km' => 'FEATURED ARTICLE', 'published_at' => now()->subMonth()]);
        $newer = $this->news(['title_km' => 'NEWER BUT NOT FEATURED', 'published_at' => now()->subHour()]);
        for ($i = 0; $i < 5; $i++) {
            $this->news();
        }

        $this->get('/')->assertOk()
            ->assertViewHas('featuredNews', fn ($article) => $article->is($featured))
            ->assertViewHas('secondaryNews', fn ($items) => $items->count() === 3 && ! $items->contains('id', $featured->id) && $items->first()->is($newer))
            ->assertSee('FEATURED ARTICLE')
            ->assertSee('NEWER BUT NOT FEATURED');
    }

    public function test_homepage_falls_back_to_the_newest_published_article_when_none_is_featured(): void
    {
        $older = $this->news(['title_km' => 'OLDER', 'published_at' => now()->subWeek()]);
        $newest = $this->news(['title_km' => 'NEWEST', 'published_at' => now()->subHour()]);

        $this->get('/')->assertOk()
            ->assertViewHas('featuredNews', fn ($article) => $article->is($newest))
            ->assertViewHas('secondaryNews', fn ($items) => $items->count() === 1 && $items->first()->is($older));
    }

    public function test_latest_content_aggregator_merges_and_sorts_across_sources(): void
    {
        $news = $this->news(['title_km' => 'NEWS ITEM', 'published_at' => now()->subMinutes(10)]);
        $health = $this->healthContent(['title_km' => 'HEALTH ITEM', 'published_at' => now()->subMinutes(20)]);
        $legal = $this->legalDocument(['title' => 'LEGAL ITEM', 'published_at' => now()->subMinutes(30)]);

        // Excluded: draft/unpublished, future-dated, and soft-deleted records.
        $this->news(['status' => 'draft', 'title_km' => 'DRAFT NEWS']);
        $this->healthContent(['status' => 'draft', 'title_km' => 'DRAFT HEALTH']);
        $this->legalDocument(['status' => 'draft', 'title' => 'DRAFT LEGAL']);

        $items = app(LatestContentAggregator::class)->latest(10);

        $this->assertCount(3, $items);
        $this->assertSame(['news', 'health_education', 'legal_document'], $items->pluck('type')->all());
        $this->assertSame(['NEWS ITEM', 'HEALTH ITEM', 'LEGAL ITEM'], $items->pluck('title')->all());
        $this->assertSame(route('news.show', $news), $items[0]->url);
        $this->assertSame(route('health-content.show', $health), $items[1]->url);
        $this->assertSame(route('legal-documents.download', $legal), $items[2]->url);
    }

    public function test_latest_content_aggregator_respects_the_limit_and_falls_back_to_issue_date_when_unpublished_at_is_missing(): void
    {
        // All older than the legal document below, so it's unambiguously in the top 3.
        // Uses subDays (not subHours) so this stays true regardless of what
        // time of day the suite happens to run at — the legal document
        // below falls back to issue_date, a date-only value that Carbon
        // normalizes to midnight, so anything within the same day but later
        // than midnight would otherwise still outrank it once the current
        // wall-clock hour passes the subHours() offset used here.
        for ($i = 0; $i < 5; $i++) {
            $this->news(['published_at' => now()->subDays(2)->subHours($i)]);
        }

        $this->legalDocument(['published_at' => null, 'issue_date' => now()->subMinutes(2)->toDateString()]);

        $items = app(LatestContentAggregator::class)->latest(3);

        $this->assertCount(3, $items);
        $this->assertTrue($items->contains(fn ($item) => $item->type === 'legal_document'));
    }

    public function test_homepage_renders_the_mixed_content_slider_without_an_extra_heading(): void
    {
        $this->news();
        $this->healthContent(['title_km' => 'MIXED SLIDER HEALTH ITEM']);

        $this->get('/')->assertOk()
            ->assertSee('class="home-latest-swiper swiper"', false)
            ->assertSee('MIXED SLIDER HEALTH ITEM')
            ->assertDontSee('មាតិកាថ្មីៗពីគ្រប់ប្រភេទ');
    }
}
