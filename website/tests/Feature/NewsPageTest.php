<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\NewsCategory;
use Database\Seeders\NewsCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsPageTest extends TestCase
{
    use RefreshDatabase;

    private function article(array $attributes = []): News
    {
        return News::create(array_merge([
            'title_km' => 'ព័ត៌មានសុខភាព',
            'slug' => 'news-'.fake()->uuid(),
            'excerpt' => 'សកម្មភាពសុខភាពសហគមន៍',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $attributes));
    }

    public function test_only_published_articles_appear_and_featured_articles_take_priority(): void
    {
        $featured = $this->article(['is_featured' => true, 'published_at' => now()->subMonth()]);
        for ($i = 0; $i < 7; $i++) {
            $this->article();
        }
        $draft = $this->article(['status' => 'draft', 'title_km' => 'PRIVATE DRAFT']);
        $future = $this->article(['published_at' => now()->addDay(), 'title_km' => 'FUTURE ARTICLE']);
        $this->article(['title_km' => 'DELETED ARTICLE'])->delete();

        $this->get('/news')->assertOk()
            ->assertViewHas('featuredArticles', fn ($items) => $items->count() === 5 && $items->first()->is($featured))
            ->assertViewHas('articles', fn ($items) => $items->count() === 8 && $items->total() === 8)
            ->assertDontSee('PRIVATE DRAFT')->assertDontSee('FUTURE ARTICLE')->assertDontSee('DELETED ARTICLE');
        $this->get('/news/'.$draft->slug)->assertNotFound();
        $this->get('/news/'.$future->slug)->assertNotFound();
    }

    public function test_database_categories_filter_listing_and_survive_pagination(): void
    {
        $category = NewsCategory::create(['name_km' => 'ប្រភេទថ្មីពីទិន្នន័យ', 'slug' => 'custom-category']);
        for ($i = 0; $i < 10; $i++) {
            $this->article(['category_id' => $category->id]);
        }
        $this->article();
        $this->article(['category_id' => $category->id, 'status' => 'draft']);

        $this->get('/news?category=custom-category')->assertOk()
            ->assertSee($category->name_km)
            ->assertSee('category=custom-category&amp;page=2#articles', false)
            ->assertViewHas('articles', fn ($items) => $items->total() === 10 && $items->every(fn ($item) => $item->category_id === $category->id))
            ->assertViewHas('categories', fn ($items) => $items->first()->news_count === 10);
        $this->get('/news?category=custom-category&page=2')->assertOk()
            ->assertViewHas('articles', fn ($items) => $items->count() === 1);
        $this->get('/news?category=missing')->assertOk()->assertSee('មិនមានព័ត៌មានក្នុងប្រភេទនេះទេ។');
    }

    public function test_empty_and_single_article_states_render_without_fake_slides(): void
    {
        $this->get('/news')->assertOk()->assertDontSee('class="swiper news-swiper"', false);
        $this->article();
        $this->get('/news')->assertOk()
            ->assertViewHas('featuredArticles', fn ($items) => $items->count() === 1)
            ->assertDontSee('class="news-slider-controls"', false);
    }

    // --- Redesigned index (featured + sidebar + 3-column grid) ---

    public function test_index_uses_the_redesigned_structure_in_mobile_friendly_order(): void
    {
        $this->article(['is_featured' => true]);
        $this->article();

        $html = $this->get('/news')->assertOk()
            ->assertSee('news-index-layout', false)
            ->assertSee('news-article-grid', false)
            ->assertSee('news-index-pager', false)
            ->assertSee('public-header', false)
            ->assertSee('public-footer', false)
            ->getContent();

        // Source order = mobile order: featured, categories + statistics, latest grid, pagination bar.
        $positions = array_map(fn ($needle) => strpos($html, $needle), ['class="news-featured"', 'class="news-sidebar"', 'id="articles"', 'class="news-index-pager"']);
        $this->assertSame($positions, collect($positions)->sort()->values()->all());
        $this->assertNotContains(false, $positions);
    }

    public function test_page_shows_nine_cards_per_page_with_summary_and_no_duplicates(): void
    {
        for ($i = 1; $i <= 11; $i++) {
            $this->article(['title_km' => sprintf('ព័ត៌មានលំដាប់ %02d', $i), 'published_at' => now()->subDays($i)]);
        }

        $page1 = $this->get('/news')->assertOk()
            ->assertViewHas('articles', fn ($items) => $items->count() === 9 && $items->total() === 11)
            ->assertSee('បង្ហាញ ១-៩ នៃ ១១ ព័ត៌មាន')->assertSee('ទំព័រ ១ នៃ ២');
        $page2 = $this->get('/news?page=2')->assertOk()
            ->assertViewHas('articles', fn ($items) => $items->count() === 2)
            ->assertSee('បង្ហាញ ១០-១១ នៃ ១១ ព័ត៌មាន');

        $grid = fn ($response) => substr($response->getContent(), strpos($response->getContent(), 'class="news-article-grid"'));
        preg_match_all('/<article class="news-article-card">.*?<h3><a href="[^"]+">([^<]+)</s', $grid($page1), $first);
        preg_match_all('/<article class="news-article-card">.*?<h3><a href="[^"]+">([^<]+)</s', $grid($page2), $second);
        $this->assertCount(9, array_unique($first[1]));
        $this->assertCount(2, array_unique($second[1]));
        $this->assertEmpty(array_intersect($first[1], $second[1]));
    }

    public function test_cards_link_image_title_and_read_more_to_the_same_detail_route(): void
    {
        $article = $this->article(['title_km' => 'ចំណងជើងសាកល្បងកាត']);
        $url = route('news.show', $article);

        $html = $this->get('/news')->assertOk()->getContent();
        $card = substr($html, strpos($html, '<article class="news-article-card">'));
        $card = substr($card, 0, strpos($card, '</article>'));

        $this->assertSame(3, substr_count($card, 'href="'.$url.'"'));
        $this->assertStringContainsString('loading="lazy"', $card);
        $this->assertStringContainsString('អានបន្ត', $card);
    }

    public function test_card_meta_shows_real_view_counts_and_hides_zero(): void
    {
        $this->article(['title_km' => 'មានអ្នកមើល', 'views_count' => 1234]);
        $this->article(['title_km' => 'មិនទាន់មានអ្នកមើល', 'views_count' => 0, 'published_at' => now()->subDays(3)]);

        $html = $this->get('/news')->assertOk()->getContent();
        $grid = substr($html, strpos($html, 'class="news-article-grid"'));

        $this->assertStringContainsString('១,២៣៤', $grid);
        preg_match_all('/news-card-views">.*?<\/svg>([^<]+)</s', $grid, $views);
        $this->assertNotEmpty($views[1]);
        foreach ($views[1] as $text) {
            $this->assertDoesNotMatchRegularExpression('/[0-9]/', $text, 'view counts must use Khmer digits');
        }
        $this->assertSame(1, substr_count($grid, 'news-card-views'));
    }

    public function test_excerpt_falls_back_to_plain_text_of_the_body_and_is_clamped(): void
    {
        $this->article(['title_km' => 'គ្មានសង្ខេប', 'excerpt' => null, 'content' => '<p>ខ្លឹមសារ <b>ដិត</b> សាកល្បង</p>'.str_repeat(' វែង', 100)]);

        $html = $this->get('/news')->assertOk()->getContent();

        $this->assertStringContainsString('ខ្លឹមសារ ដិត សាកល្បង', $html);
        $this->assertStringNotContainsString('<b>ដិត</b>', $html);
        preg_match('/news-card-excerpt">([^<]*)</u', $html, $excerpt);
        $this->assertLessThanOrEqual(183, mb_strlen($excerpt[1]));
    }

    public function test_category_sidebar_counts_are_real_and_links_use_the_existing_query(): void
    {
        $meeting = NewsCategory::create(['name_km' => 'សកម្មភាពសាកល្បង', 'slug' => 'activity-test']);
        $this->article(['category_id' => $meeting->id]);
        $this->article(['category_id' => $meeting->id]);
        $this->article(['category_id' => $meeting->id, 'status' => 'draft']);
        $this->article();

        $html = $this->get('/news')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<span>ព័ត៌មានទាំងអស់<\/span><span class="news-category-count">៣</u', $html);
        $this->assertMatchesRegularExpression('/<span>សកម្មភាពសាកល្បង<\/span><span class="news-category-count">២</u', $html);
        $this->assertStringContainsString('category=activity-test#articles', $html);
        $this->assertStringContainsString('news-category-icon', $html);
        $this->assertStringContainsString('news-category-toggle', $html);
    }

    public function test_statistics_card_only_shows_the_existing_visitor_statistics(): void
    {
        $html = $this->get('/news')->assertOk()->getContent();

        foreach (['online', 'today', 'yesterday', 'month', 'total', 'page_views'] as $key) {
            $this->assertStringContainsString('data-stat="'.$key.'"', $html);
        }
        $this->assertStringContainsString('data-statistics-url="'.route('visitor-statistics').'"', $html);
        $this->assertStringContainsString('news-stat-icon', $html);
    }

    public function test_article_page_keeps_the_plain_sidebar_components(): void
    {
        $article = $this->article();

        $this->get(route('news.show', $article))->assertOk()
            ->assertDontSee('news-category-toggle', false)
            ->assertDontSee('news-stat-icon', false)
            ->assertSee('data-stat="online"', false);
    }

    public function test_featured_slider_shows_views_and_a_clickable_title(): void
    {
        $featured = $this->article(['title_km' => 'ព័ត៌មានលេចធ្លោសាកល្បង', 'is_featured' => true, 'views_count' => 428]);
        $this->article(['title_km' => 'ព័ត៌មានទីពីរ']);

        $html = $this->get('/news')->assertOk()->getContent();
        $slide = substr($html, strpos($html, 'class="swiper-slide news-slide"'));
        $slide = substr($slide, 0, strpos($slide, '</article>'));

        $this->assertStringContainsString('<h3><a href="'.route('news.show', $featured).'">ព័ត៌មានលេចធ្លោសាកល្បង</a></h3>', $slide);
        $this->assertStringContainsString('៤២៨', $slide);
        $this->assertStringNotContainsString('428', $slide);
        $this->assertStringContainsString('អានបន្ថែម', $slide);
    }

    public function test_empty_category_shows_the_empty_state_instead_of_a_blank_grid(): void
    {
        NewsCategory::create(['name_km' => 'ប្រភេទទទេ', 'slug' => 'empty-category']);
        $this->article();

        $this->get('/news?category=empty-category')->assertOk()
            ->assertSee('មិនមានព័ត៌មានក្នុងប្រភេទនេះទេ។')
            ->assertDontSee('class="news-article-grid"', false)
            ->assertDontSee('news-index-pager"', false);
    }

    public function test_category_seed_is_repeatable_and_preserves_existing_edits(): void
    {
        NewsCategory::create(['name_km' => 'ឈ្មោះដែលបានកែ', 'slug' => 'meeting', 'sort_order' => 20]);
        $this->seed(NewsCategorySeeder::class);
        $this->seed(NewsCategorySeeder::class);
        $this->assertDatabaseCount('news_categories', 7);
        $this->assertDatabaseHas('news_categories', ['slug' => 'meeting', 'name_km' => 'ឈ្មោះដែលបានកែ', 'sort_order' => 20]);
    }
}
