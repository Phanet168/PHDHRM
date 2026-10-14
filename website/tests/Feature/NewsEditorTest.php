<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\News;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the rich-editor / tabs / SEO sub-phase of the News admin redesign:
 * meta_title/meta_description persistence, content sanitization on save,
 * the rich-editor textarea markup, and the admin.media.picker JSON endpoint
 * it fetches images from.
 */
class NewsEditorTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    private function news(array $attributes = []): News
    {
        static $n = 0;
        $n++;

        return News::create(array_merge([
            'title_km' => 'អត្ថបទសាកល្បង '.$n, 'slug' => 'editor-news-'.$n, 'status' => 'draft',
        ], $attributes));
    }

    // --- SEO fields ---

    public function test_meta_title_and_meta_description_are_saved_when_creating_an_article(): void
    {
        $this->actingAs($this->admin())->post(route('admin.news.store'), [
            'title_km' => 'អត្ថបទ SEO', 'slug' => 'seo-article',
            'meta_title' => 'ចំណងជើង SEO ផ្ទាល់ខ្លួន',
            'meta_description' => 'សេចក្តីពិពណ៌នា SEO ផ្ទាល់ខ្លួន',
        ])->assertRedirect();

        $news = News::where('slug', 'seo-article')->firstOrFail();
        $this->assertSame('ចំណងជើង SEO ផ្ទាល់ខ្លួន', $news->meta_title);
        $this->assertSame('សេចក្តីពិពណ៌នា SEO ផ្ទាល់ខ្លួន', $news->meta_description);
    }

    public function test_meta_title_and_meta_description_can_be_updated(): void
    {
        $news = $this->news();

        $this->actingAs($this->admin())->put(route('admin.news.update', $news), [
            'title_km' => $news->title_km, 'slug' => $news->slug,
            'meta_title' => 'ចំណងជើងថ្មី', 'meta_description' => 'ការពិពណ៌នាថ្មី',
        ])->assertRedirect();

        $this->assertSame('ចំណងជើងថ្មី', $news->fresh()->meta_title);
        $this->assertSame('ការពិពណ៌នាថ្មី', $news->fresh()->meta_description);
    }

    public function test_meta_title_is_rejected_beyond_255_characters(): void
    {
        $this->actingAs($this->admin())->post(route('admin.news.store'), [
            'title_km' => 'អត្ថបទ', 'slug' => 'too-long-meta-title',
            'meta_title' => str_repeat('a', 256),
        ])->assertSessionHasErrors('meta_title');

        $this->assertNull(News::where('slug', 'too-long-meta-title')->first());
    }

    public function test_meta_description_is_rejected_beyond_500_characters(): void
    {
        $this->actingAs($this->admin())->post(route('admin.news.store'), [
            'title_km' => 'អត្ថបទ', 'slug' => 'too-long-meta-description',
            'meta_description' => str_repeat('a', 501),
        ])->assertSessionHasErrors('meta_description');

        $this->assertNull(News::where('slug', 'too-long-meta-description')->first());
    }

    public function test_meta_fields_are_optional(): void
    {
        $this->actingAs($this->admin())->post(route('admin.news.store'), [
            'title_km' => 'អត្ថបទគ្មាន SEO', 'slug' => 'no-seo-article',
        ])->assertRedirect();

        $news = News::where('slug', 'no-seo-article')->firstOrFail();
        $this->assertNull($news->meta_title);
        $this->assertNull($news->meta_description);
    }

    // --- Content sanitization (rich editor saves raw HTML; server re-sanitizes) ---

    public function test_script_tags_are_stripped_from_content_on_save(): void
    {
        $this->actingAs($this->admin())->post(route('admin.news.store'), [
            'title_km' => 'អត្ថបទគ្រោះថ្នាក់', 'slug' => 'dangerous-content',
            'content' => '<p>អត្ថបទធម្មតា</p><script>alert("xss")</script>',
        ])->assertRedirect();

        $news = News::where('slug', 'dangerous-content')->firstOrFail();
        $this->assertStringNotContainsString('<script', $news->content);
        $this->assertStringNotContainsString('alert', $news->content);
        $this->assertStringContainsString('អត្ថបទធម្មតា', $news->content);
    }

    public function test_inline_event_handlers_are_stripped_from_content_on_save(): void
    {
        $this->actingAs($this->admin())->post(route('admin.news.store'), [
            'title_km' => 'អត្ថបទ onerror', 'slug' => 'onerror-content',
            'content' => '<p>អត្ថបទ</p><img src="x" onerror="alert(1)">',
        ])->assertRedirect();

        $news = News::where('slug', 'onerror-content')->firstOrFail();
        $this->assertStringNotContainsString('onerror', $news->content);
    }

    public function test_legitimate_rich_formatting_survives_sanitization(): void
    {
        $this->actingAs($this->admin())->post(route('admin.news.store'), [
            'title_km' => 'អត្ថបទរូបរាង', 'slug' => 'rich-formatted-content',
            'content' => '<p><b>ដិត</b> និង <i>ទ្រេត</i></p><h2>ចំណងជើងរង</h2>'
                .'<table><tbody><tr><td>ក</td><td>ខ</td></tr></tbody></table>'
                .'<ul><li>មួយ</li><li>ពីរ</li></ul>',
        ])->assertRedirect();

        $news = News::where('slug', 'rich-formatted-content')->firstOrFail();
        $this->assertStringContainsString('<b>ដិត</b>', $news->content);
        $this->assertStringContainsString('<h2>ចំណងជើងរង</h2>', $news->content);
        $this->assertStringContainsString('<table>', $news->content);
        $this->assertStringContainsString('<li>មួយ</li>', $news->content);
    }

    public function test_content_is_re_sanitized_on_update_too(): void
    {
        $news = $this->news(['content' => '<p>ដើម</p>']);

        $this->actingAs($this->admin())->put(route('admin.news.update', $news), [
            'title_km' => $news->title_km, 'slug' => $news->slug,
            'content' => '<p>ថ្មី</p><script>alert(1)</script>',
        ])->assertRedirect();

        $this->assertStringNotContainsString('<script', $news->fresh()->content);
        $this->assertStringContainsString('ថ្មី', $news->fresh()->content);
    }

    // --- Rich editor markup ---

    public function test_create_page_renders_the_rich_editor_textarea(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.news.create'));

        $response->assertOk();
        $response->assertSee('data-tiptap', false);
        $response->assertSee('data-media-picker-url', false);
    }

    public function test_edit_page_renders_the_rich_editor_textarea_with_existing_content(): void
    {
        $news = $this->news(['content' => '<p>ខ្លឹមសារដើម</p>']);

        $response = $this->actingAs($this->admin())->get(route('admin.news.edit', $news));

        $response->assertOk();
        $response->assertSee('data-tiptap', false);
        $response->assertSee('ខ្លឹមសារដើម', false);
    }

    public function test_create_and_edit_pages_render_all_four_tabs(): void
    {
        $news = $this->news();

        foreach ([route('admin.news.create'), route('admin.news.edit', $news)] as $url) {
            $response = $this->actingAs($this->admin())->get($url);
            $response->assertOk();
            $response->assertSee('មាតិកា', false);
            $response->assertSee('រូបភាព និងឯកសារ', false);
            $response->assertSee('ការកំណត់ផ្សេងៗ', false);
            $response->assertSee('SEO និងការបោះផ្សាយ', false);
        }
    }

    // --- admin.media.picker JSON endpoint (used by the rich editor's image-insert toolbar button) ---

    public function test_media_picker_endpoint_requires_authentication(): void
    {
        $this->getJson(route('admin.media.picker'))->assertUnauthorized();
    }

    public function test_media_picker_endpoint_is_forbidden_without_media_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        // No role assigned: no media.manage/media.view ability.

        $this->actingAs($user)->getJson(route('admin.media.picker'))->assertForbidden();
    }

    public function test_media_picker_endpoint_returns_only_images_with_id_name_url(): void
    {
        Media::create(['disk_path' => 'library/photo.jpg', 'original_filename' => 'photo.jpg', 'mime_type' => 'image/jpeg', 'size' => 2048]);
        Media::create(['disk_path' => 'library/report.pdf', 'original_filename' => 'report.pdf', 'mime_type' => 'application/pdf', 'size' => 4096]);

        $response = $this->actingAs($this->admin())->getJson(route('admin.media.picker'));

        $response->assertOk();
        $items = $response->json();
        $this->assertCount(1, $items);
        $this->assertSame('photo.jpg', $items[0]['name']);
        $this->assertArrayHasKey('id', $items[0]);
        $this->assertArrayHasKey('url', $items[0]);
    }
}
