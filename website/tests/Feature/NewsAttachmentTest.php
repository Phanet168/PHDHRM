<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\News;
use App\Models\NewsAttachment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    private function document(string $name = 'doc.pdf', string $mime = 'application/pdf'): Media
    {
        return Media::create(['disk_path' => 'library/'.$name, 'original_filename' => $name, 'mime_type' => $mime, 'size' => 1024]);
    }

    private function news(array $attributes = []): News
    {
        static $n = 0;
        $n++;

        return News::create(array_merge([
            'title_km' => 'ព័ត៌មានឯកសារភ្ជាប់ '.$n, 'slug' => 'attach-news-'.$n, 'status' => 'draft',
        ], $attributes));
    }

    // --- Create/update with attachments, separate from the gallery ---

    public function test_attachments_can_be_uploaded_when_creating_an_article(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post(route('admin.news.store'), [
            'title_km' => 'អត្ថបទមានឯកសារ', 'slug' => 'article-with-attachment',
            'attachments' => [UploadedFile::fake()->create('report.pdf', 200, 'application/pdf')],
        ])->assertRedirect();

        $news = News::where('slug', 'article-with-attachment')->firstOrFail();
        $this->assertCount(1, $news->attachments);
        $this->assertNotNull($news->attachments->first()->media_id);
        $this->assertSame(0, $news->images->count(), 'attachments must not land in the image gallery');
    }

    public function test_existing_library_documents_can_be_attached_without_duplicating_files(): void
    {
        $admin = $this->admin();
        $news = $this->news();
        $doc = $this->document();

        $this->actingAs($admin)->post(route('admin.news.attachments.from-media', $news), [
            'media_ids' => [$doc->id],
        ])->assertRedirect();

        $this->assertCount(1, $news->fresh()->attachments);
        $this->assertSame($doc->id, $news->attachments()->first()->media_id);
        $this->assertSame(1, Media::where('disk_path', $doc->disk_path)->count());
    }

    public function test_attaching_the_same_document_twice_does_not_duplicate_the_row(): void
    {
        $admin = $this->admin();
        $news = $this->news();
        $doc = $this->document();

        $this->actingAs($admin)->post(route('admin.news.attachments.from-media', $news), ['media_ids' => [$doc->id]]);
        $this->actingAs($admin)->post(route('admin.news.attachments.from-media', $news), ['media_ids' => [$doc->id]]);

        $this->assertCount(1, $news->fresh()->attachments);
    }

    // --- Rename (label) ---

    public function test_an_attachment_can_be_renamed_without_touching_the_media_file(): void
    {
        $admin = $this->admin();
        $news = $this->news();
        $doc = $this->document();
        $attachment = $news->attachments()->create(['media_id' => $doc->id, 'sort_order' => 0]);

        $this->actingAs($admin)->put(route('admin.news.attachments.update', [$news, $attachment]), [
            'label' => 'របាយការណ៍ប្រចាំខែ',
        ])->assertRedirect();

        $this->assertSame('របាយការណ៍ប្រចាំខែ', $attachment->fresh()->displayName());
        $this->assertSame($doc->original_filename, $doc->fresh()->original_filename);
    }

    public function test_display_name_falls_back_to_the_media_filename_when_no_label_is_set(): void
    {
        $doc = $this->document('quarterly-report.pdf');
        $attachment = new NewsAttachment(['media_id' => $doc->id]);
        $attachment->setRelation('media', $doc);

        $this->assertSame('quarterly-report.pdf', $attachment->displayName());
    }

    // --- Remove (detach only) ---

    public function test_removing_an_attachment_only_detaches_it_never_deletes_the_media(): void
    {
        $admin = $this->admin();
        $news = $this->news();
        $doc = $this->document();
        $attachment = $news->attachments()->create(['media_id' => $doc->id, 'sort_order' => 0]);

        $this->actingAs($admin)->delete(route('admin.news.attachments.destroy', [$news, $attachment]))->assertRedirect();

        $this->assertDatabaseMissing('news_attachments', ['id' => $attachment->id]);
        $this->assertDatabaseHas('media', ['id' => $doc->id]);
    }

    public function test_removing_an_attachment_from_a_different_article_is_rejected(): void
    {
        $admin = $this->admin();
        $newsOne = $this->news();
        $newsTwo = $this->news();
        $attachment = $newsOne->attachments()->create(['media_id' => $this->document()->id, 'sort_order' => 0]);

        $this->actingAs($admin)->delete(route('admin.news.attachments.destroy', [$newsTwo, $attachment]))->assertNotFound();
        $this->assertDatabaseHas('news_attachments', ['id' => $attachment->id]);
    }

    public function test_attachment_actions_require_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $news = $this->news();
        $doc = $this->document();
        $attachment = $news->attachments()->create(['media_id' => $doc->id, 'sort_order' => 0]);

        $reviewer = User::factory()->create(['is_active' => true]);
        $reviewer->assignRole('Reviewer');

        $this->actingAs($reviewer)->post(route('admin.news.attachments.from-media', $news), ['media_ids' => [$doc->id]])->assertForbidden();
        $this->actingAs($reviewer)->delete(route('admin.news.attachments.destroy', [$news, $attachment]))->assertForbidden();
    }

    // --- Reorder ---

    public function test_attachments_can_be_reordered(): void
    {
        $admin = $this->admin();
        $news = $this->news();
        $a = $news->attachments()->create(['media_id' => $this->document('a.pdf')->id, 'sort_order' => 0]);
        $b = $news->attachments()->create(['media_id' => $this->document('b.pdf')->id, 'sort_order' => 1]);

        $this->actingAs($admin)->postJson(route('admin.news.attachments.reorder', $news), [
            'items' => [['id' => $b->id, 'sort_order' => 0], ['id' => $a->id, 'sort_order' => 1]],
        ])->assertOk();

        $ordered = $news->fresh()->attachments;
        $this->assertSame($b->id, $ordered->first()->id);
    }

    // --- Validation: only document/image/video mimes accepted, matching the Media Library ---

    public function test_disallowed_file_types_are_rejected_on_upload(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post(route('admin.news.store'), [
            'title_km' => 'អត្ថបទឯកសារខុស', 'slug' => 'article-bad-attachment',
            'attachments' => [UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload')],
        ])->assertSessionHasErrors('attachments.0');

        $this->assertSame(0, News::where('slug', 'article-bad-attachment')->count());
    }

    // --- forceDestroy does not delete Media ---

    public function test_permanently_deleting_an_article_does_not_delete_its_attached_media(): void
    {
        $admin = $this->admin();
        $news = $this->news();
        $doc = $this->document();
        $news->attachments()->create(['media_id' => $doc->id, 'sort_order' => 0]);
        $news->delete();

        $this->actingAs($admin)->delete(route('admin.news.force-destroy', $news))->assertRedirect();

        $this->assertDatabaseMissing('news', ['id' => $news->id]);
        $this->assertDatabaseMissing('news_attachments', ['news_id' => $news->id]);
        $this->assertDatabaseHas('media', ['id' => $doc->id]);
    }
}
