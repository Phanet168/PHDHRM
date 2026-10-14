<?php

namespace Tests\Feature;

use App\Models\LeaderRecommendation;
use App\Models\LeaderRecommendationItem;
use App\Models\Media;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeaderRecommendationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    public function test_homepage_shows_only_the_active_and_published_recommendation_with_all_active_items(): void
    {
        $admin = $this->admin();

        $draft = LeaderRecommendation::create(['title_km' => 'សេចក្តីព្រាង', 'leader_name_km' => 'ក', 'is_active' => false]);
        $future = LeaderRecommendation::create(['title_km' => 'អនាគត', 'leader_name_km' => 'ខ', 'is_active' => true, 'published_at' => now()->addDay()]);
        $live = LeaderRecommendation::create(['title_km' => 'អនុសាសន៍ថ្នាក់ដឹកនាំ', 'leader_name_km' => 'លោក នាយក', 'leader_position_km' => 'នាយកមន្ទីរ', 'is_active' => true, 'published_at' => now()->subDay()]);

        foreach (range(1, 7) as $i) {
            LeaderRecommendationItem::create(['recommendation_id' => $live->id, 'content_km' => "អនុសាសន៍ទី {$i}", 'sort_order' => $i, 'is_active' => true]);
        }
        // Inactive items should never render, regardless of sort position.
        LeaderRecommendationItem::create(['recommendation_id' => $live->id, 'content_km' => 'បិទ', 'sort_order' => 0, 'is_active' => false]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('អនុសាសន៍ថ្នាក់ដឹកនាំ');
        $response->assertSee('លោក នាយក');
        $response->assertSee('នាយកមន្ទីរ');
        $response->assertDontSee('សេចក្តីព្រាង');
        $response->assertDontSee('អនាគត');
        $response->assertDontSee('បិទ');
        // All active items render server-side; a CSS breakpoint (not PHP) hides
        // items past the 5th on narrow viewports so the desktop 2-column grid
        // can still show the full set.
        $response->assertSee('អនុសាសន៍ទី 1');
        $response->assertSee('អនុសាសន៍ទី 7');
        $response->assertSee('មើលអនុសាសន៍ទាំងអស់');
    }

    public function test_detail_page_shows_all_active_items_but_404s_when_unpublished(): void
    {
        $recommendation = LeaderRecommendation::create(['title_km' => 'ត', 'leader_name_km' => 'ក', 'is_active' => true, 'published_at' => now()->subHour()]);
        LeaderRecommendationItem::create(['recommendation_id' => $recommendation->id, 'content_km' => 'ចំណុចទី១', 'sort_order' => 0, 'is_active' => true]);
        LeaderRecommendationItem::create(['recommendation_id' => $recommendation->id, 'content_km' => 'ចំណុចលាក់', 'sort_order' => 1, 'is_active' => false]);

        $this->get(route('leader-recommendations.show', $recommendation))
            ->assertOk()
            ->assertSee('ចំណុចទី១')
            ->assertDontSee('ចំណុចលាក់');

        $recommendation->update(['is_active' => false]);
        $this->get(route('leader-recommendations.show', $recommendation))->assertNotFound();
    }

    public function test_admin_can_create_recommendation_select_media_from_library_and_manage_items(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        Storage::disk('public')->put('library/leader.jpg', 'x');
        $media = Media::create(['disk_path' => 'library/leader.jpg', 'original_filename' => 'leader.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);

        $response = $this->actingAs($admin)->post(route('admin.leader-recommendations.store'), [
            'title_km' => 'អនុសាសន៍ថ្នាក់ដឹកនាំ',
            'leader_name_km' => 'លោក នាយក',
            'leader_position_km' => 'នាយកមន្ទីរ',
            'is_active' => '1',
        ]);
        $recommendation = LeaderRecommendation::first();
        $response->assertRedirect(route('admin.leader-recommendations.edit', $recommendation));

        // Reusing an existing Media Library file, not a fresh upload.
        $this->actingAs($admin)->put(route('admin.leader-recommendations.update', $recommendation), [
            'title_km' => $recommendation->title_km,
            'leader_name_km' => $recommendation->leader_name_km,
            'media_id' => $media->id,
            'is_active' => '1',
        ]);
        $this->assertSame($media->id, $recommendation->fresh()->media_id);
        $this->assertSame(1, Media::count()); // no duplicate file/record was created

        // Add items and reorder them.
        $this->actingAs($admin)->post(route('admin.leader-recommendations.items.store', $recommendation), ['content_km' => 'ទីមួយ']);
        $this->actingAs($admin)->post(route('admin.leader-recommendations.items.store', $recommendation), ['content_km' => 'ទីពីរ']);
        $items = $recommendation->items()->orderBy('sort_order')->get();
        $this->assertSame('ទីមួយ', $items[0]->content_km);

        $this->actingAs($admin)->postJson(route('admin.leader-recommendations.items.reorder', $recommendation), [
            'items' => [
                ['id' => $items[0]->id, 'sort_order' => 1],
                ['id' => $items[1]->id, 'sort_order' => 0],
            ],
        ])->assertOk();
        $this->assertSame('ទីពីរ', $recommendation->items()->orderBy('sort_order')->first()->content_km);

        // Deactivate the whole set -> no longer eligible for the homepage.
        $this->actingAs($admin)->post(route('admin.leader-recommendations.unpublish', $recommendation));
        $this->assertFalse($recommendation->fresh()->is_active);
    }

    public function test_moving_the_leader_media_to_another_folder_does_not_break_the_homepage_section(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        Storage::disk('public')->put('library/leader.jpg', 'x');
        $media = Media::create(['disk_path' => 'library/leader.jpg', 'original_filename' => 'leader.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
        $recommendation = LeaderRecommendation::create(['title_km' => 'ត', 'leader_name_km' => 'ក', 'is_active' => true, 'published_at' => now()->subDay(), 'media_id' => $media->id]);

        $folder = \App\Models\MediaFolder::create(['name' => 'ថតថ្មី', 'slug' => 'lr-folder']);
        $media->update(['folder_id' => $folder->id, 'disk_path' => 'library/renamed.jpg']);

        $this->assertSame($media->id, $recommendation->fresh()->media_id);
        $this->assertStringContainsString('renamed.jpg', $recommendation->fresh()->leaderImageUrl());
    }

    public function test_only_users_with_the_permission_can_manage_recommendations(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $editor = User::factory()->create(['is_active' => true]);
        $editor->assignRole('Editor'); // Editor role is not given leader-recommendations.manage

        $this->actingAs($editor)->get(route('admin.leader-recommendations.index'))->assertForbidden();
    }
}
