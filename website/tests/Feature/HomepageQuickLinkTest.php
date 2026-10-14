<?php

namespace Tests\Feature;

use App\Models\HomepageQuickLink;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomepageQuickLinkTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    private function image(array $attributes = []): Media
    {
        Storage::fake('public');
        Storage::disk('public')->put('library/quick-link.jpg', 'x');

        return Media::create(array_merge([
            'disk_path' => 'library/quick-link.jpg',
            'original_filename' => 'quick-link.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1,
        ], $attributes));
    }

    private function link(array $attributes = []): HomepageQuickLink
    {
        return HomepageQuickLink::create(array_merge([
            'title_km' => 'មូលដ្ឋានសុខាភិបាល',
            'icon' => 'pin',
            'link_type' => 'route',
            'route_name' => 'facilities.index',
            'is_active' => true,
        ], $attributes));
    }

    public function test_admin_can_save_a_valid_media_id(): void
    {
        $admin = $this->admin();
        $media = $this->image();
        $link = $this->link();

        $this->actingAs($admin)->put(route('admin.homepage-quick-links.update', $link), [
            'title_km' => $link->title_km, 'icon' => 'pin', 'link_type' => 'route', 'route_name' => 'facilities.index',
            'is_active' => '1', 'media_id' => $media->id,
        ])->assertRedirect(route('admin.homepage-quick-links.edit', $link));

        $this->assertSame($media->id, $link->fresh()->media_id);
    }

    public function test_saving_the_main_info_form_does_not_clear_a_previously_selected_image(): void
    {
        // Regression: the info form (title/description/icon/etc.) has no
        // media_id field at all, since the image picker lives in a separate
        // form. Submitting the info form must leave media_id untouched.
        $admin = $this->admin();
        $media = $this->image();
        $link = $this->link(['media_id' => $media->id]);

        $this->actingAs($admin)->put(route('admin.homepage-quick-links.update', $link), [
            'title_km' => 'ចំណងជើងថ្មី', 'icon' => 'pin', 'link_type' => 'route', 'route_name' => 'facilities.index',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertSame($media->id, $link->fresh()->media_id);
        $this->assertSame('ចំណងជើងថ្មី', $link->fresh()->title_km);
    }

    public function test_non_image_media_id_is_rejected(): void
    {
        $admin = $this->admin();
        $pdf = $this->image(['disk_path' => 'library/doc.pdf', 'original_filename' => 'doc.pdf', 'mime_type' => 'application/pdf']);
        $link = $this->link();

        $this->actingAs($admin)->put(route('admin.homepage-quick-links.update', $link), [
            'title_km' => $link->title_km, 'icon' => 'pin', 'link_type' => 'route', 'route_name' => 'facilities.index',
            'is_active' => '1', 'media_id' => $pdf->id,
        ])->assertStatus(422);

        $this->assertNull($link->fresh()->media_id);
    }

    public function test_invalid_media_id_is_rejected(): void
    {
        $admin = $this->admin();
        $link = $this->link();

        $this->actingAs($admin)->put(route('admin.homepage-quick-links.update', $link), [
            'title_km' => $link->title_km, 'icon' => 'pin', 'link_type' => 'route', 'route_name' => 'facilities.index',
            'is_active' => '1', 'media_id' => 999999,
        ])->assertStatus(422);

        $this->assertNull($link->fresh()->media_id);
    }

    public function test_removing_card_image_sets_media_id_to_null(): void
    {
        $admin = $this->admin();
        $media = $this->image();
        $link = $this->link(['media_id' => $media->id]);

        $this->actingAs($admin)->put(route('admin.homepage-quick-links.update', $link), [
            'title_km' => $link->title_km, 'icon' => 'pin', 'link_type' => 'route', 'route_name' => 'facilities.index',
            'is_active' => '1', 'media_id' => '',
        ])->assertRedirect();

        $this->assertNull($link->fresh()->media_id);
    }

    public function test_removing_card_image_does_not_delete_the_media_record(): void
    {
        $admin = $this->admin();
        $media = $this->image();
        $link = $this->link(['media_id' => $media->id]);

        $this->actingAs($admin)->put(route('admin.homepage-quick-links.update', $link), [
            'title_km' => $link->title_km, 'icon' => 'pin', 'link_type' => 'route', 'route_name' => 'facilities.index',
            'is_active' => '1', 'media_id' => '',
        ]);

        $this->assertNotSoftDeleted('media', ['id' => $media->id]);
        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }

    public function test_homepage_renders_the_selected_image(): void
    {
        $media = $this->image();
        $this->link(['media_id' => $media->id, 'title_km' => 'QUICK LINK WITH IMAGE']);

        $this->get('/')->assertOk()->assertSee($media->url, false);
    }

    public function test_homepage_falls_back_to_the_default_image_when_media_is_missing_or_trashed(): void
    {
        $media = $this->image();
        $link = $this->link(['media_id' => $media->id, 'title_km' => 'QUICK LINK FALLBACK']);

        $media->delete(); // soft delete, as Media Library trash does

        $response = $this->get('/')->assertOk()->assertSee('QUICK LINK FALLBACK');
        $response->assertDontSee($media->url, false);
        $this->assertNull($link->fresh()->media()->first());
    }

    public function test_moving_or_renaming_media_does_not_break_the_card_reference(): void
    {
        $media = $this->image();
        $link = $this->link(['media_id' => $media->id]);

        $folder = MediaFolder::create(['name' => 'ថតថ្មី', 'slug' => 'quick-link-folder']);
        $media->update(['folder_id' => $folder->id, 'disk_path' => 'library/renamed-quick-link.jpg']);

        $this->assertSame($media->id, $link->fresh()->media_id);
        $this->assertStringContainsString('renamed-quick-link.jpg', $link->fresh()->imageUrl());
    }

    public function test_unauthorized_user_cannot_change_the_image(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $editor = User::factory()->create(['is_active' => true]);
        $editor->assignRole('Editor'); // Editor role is not given homepage-quick-links.manage

        $media = $this->image();
        $link = $this->link();

        $this->actingAs($editor)->put(route('admin.homepage-quick-links.update', $link), [
            'title_km' => $link->title_km, 'icon' => 'pin', 'link_type' => 'route', 'route_name' => 'facilities.index',
            'is_active' => '1', 'media_id' => $media->id,
        ])->assertForbidden();

        $this->assertNull($link->fresh()->media_id);
    }
}
