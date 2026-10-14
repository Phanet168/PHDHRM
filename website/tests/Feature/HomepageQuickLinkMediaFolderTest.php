<?php

namespace Tests\Feature;

use App\Models\HomepageQuickLink;
use App\Models\MediaFolder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageQuickLinkMediaFolderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    private function link(array $attributes = []): HomepageQuickLink
    {
        return HomepageQuickLink::create(array_merge([
            'title_km' => 'តំណភ្ជាប់សាកល្បង',
            'icon' => 'pin',
            'link_type' => 'route',
            'route_name' => 'facilities.index',
            'is_active' => true,
            'sort_order' => 0,
        ], $attributes));
    }

    public function test_opening_the_edit_page_creates_and_preselects_the_links_own_folder_in_the_picker(): void
    {
        $link = $this->link();

        $response = $this->actingAs($this->admin())->get(route('admin.homepage-quick-links.edit', $link));

        $response->assertOk();

        $folder = MediaFolder::where('owner_type', HomepageQuickLink::class)->where('owner_id', $link->id)->first();
        $this->assertNotNull($folder);
        $this->assertSame("#{$link->id} — តំណភ្ជាប់សាកល្បង", $folder->name);
        $this->assertSame('តំណភ្ជាប់រហ័សទំព័រដើម', $folder->parent->name);
        $response->assertSee("x-data=\"{ folder: '{$folder->id}' }\"", false);
    }
}
