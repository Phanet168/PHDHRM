<?php

namespace Tests\Feature;

use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression test for a live bug report: opening an older News article (one saved before
 * featured_media_id existed, or seeded with a bundled demo photo) showed a broken/empty
 * Featured Image and gallery thumbnails. Root cause: featuredImageUrl()/NewsImage::url()
 * always assumed the legacy featured_image/image string lived under storage/app/public and
 * built that URL with no existence check — while a real chunk of the current News data (most
 * seeded articles) instead has its demo photo bundled as a static asset directly under public/
 * (see database/seeders/NewsSeeder.php). Media::resolveLegacyPath() now checks both
 * conventions and only ever returns a URL for a file that actually exists, else null (caller
 * falls back to the site default image) — this file proves that end-to-end.
 */
class NewsImageFallbackTest extends TestCase
{
    use RefreshDatabase;

    private function news(array $attributes = []): News
    {
        static $n = 0;
        $n++;

        return News::create(array_merge([
            'title_km' => 'អត្ថបទសាកល្បង '.$n, 'slug' => 'fallback-news-'.$n, 'status' => 'draft',
        ], $attributes));
    }

    public function test_featured_image_resolves_a_legacy_path_stored_under_storage_disk(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('news/legacy/cover.jpg', 'fake-bytes');

        $news = $this->news(['featured_image' => 'news/legacy/cover.jpg']);

        $this->assertStringContainsString('storage/news/legacy/cover.jpg', $news->featuredImageUrl());
    }

    public function test_featured_image_resolves_a_legacy_path_stored_as_a_bundled_public_asset(): void
    {
        // Reproduces the real data shape: NewsSeeder bundles demo photos directly under
        // public/images/..., not under the storage disk.
        $relative = 'images/tmp-fallback-test-'.uniqid().'.jpg';
        $absolute = public_path($relative);
        file_put_contents($absolute, 'fake-bytes');

        try {
            $news = $this->news(['featured_image' => $relative]);

            $url = $news->featuredImageUrl();
            $this->assertStringContainsString($relative, $url);
            $this->assertStringNotContainsString('storage/'.$relative, $url, 'must not prepend storage/ for a public-root asset');
        } finally {
            @unlink($absolute);
        }
    }

    public function test_featured_image_falls_back_to_the_default_when_the_legacy_file_is_missing_under_both_conventions(): void
    {
        Storage::fake('public');

        $news = $this->news(['featured_image' => 'images/this-file-was-never-uploaded-or-bundled.jpg']);

        $url = $news->featuredImageUrl();
        $this->assertStringContainsString(config('site.default_news_image'), $url);
        $this->assertStringNotContainsString('this-file-was-never-uploaded-or-bundled', $url);
    }

    public function test_gallery_image_has_the_same_two_convention_fallback_as_the_featured_image(): void
    {
        $relative = 'images/tmp-gallery-fallback-'.uniqid().'.jpg';
        $absolute = public_path($relative);
        file_put_contents($absolute, 'fake-bytes');

        try {
            $news = $this->news();
            $image = $news->images()->create(['image' => $relative, 'sort_order' => 0]);

            $this->assertStringContainsString($relative, $image->url());
        } finally {
            @unlink($absolute);
        }
    }

    public function test_gallery_image_falls_back_to_default_instead_of_rendering_broken(): void
    {
        Storage::fake('public');

        $news = $this->news();
        $image = $news->images()->create(['image' => 'images/never-existed.jpg', 'sort_order' => 0]);

        $this->assertStringContainsString(config('site.default_news_image'), $image->url());
    }
}
