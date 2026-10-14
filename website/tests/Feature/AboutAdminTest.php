<?php

namespace Tests\Feature;

use App\Models\AboutItem;
use App\Models\AboutOrgUnit;
use App\Models\AboutSection;
use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\FacilityLeader;
use App\Models\Media;
use App\Models\PageBanner;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\AboutDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AboutAdminTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'Website Admin'): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function image(string $path = 'library/about.jpg'): Media
    {
        Storage::fake('public');
        Storage::disk('public')->put($path, 'x');

        return Media::create(['disk_path' => $path, 'original_filename' => basename($path), 'mime_type' => 'image/jpeg', 'size' => 1]);
    }

    public function test_about_admin_requires_about_manage_permission(): void
    {
        $this->actingAs($this->user('Editor'))->get(route('admin.about.index'))->assertForbidden();
        $this->actingAs($this->user('Website Admin'))->get(route('admin.about.index'))->assertOk()
            ->assertSee('ទិដ្ឋភាពទូទៅ')->assertSee('មើលជាមុន')->assertSee(route('about'), false);
    }

    public function test_every_admin_screen_renders_including_empty_states(): void
    {
        $admin = $this->user();
        foreach (['cards', 'timeline', 'documents'] as $group) {
            $this->actingAs($admin)->get(route('admin.about.items.index', $group))->assertOk()->assertSee('មិនទាន់មានទិន្នន័យ')->assertSee('+ បន្ថែម');
            $this->actingAs($admin)->get(route('admin.about.items.create', $group))->assertOk();
        }
        foreach (['admin.about.leaders.index', 'admin.about.leaders.create', 'admin.about.organization.index', 'admin.about.organization.create', 'admin.about.contact.edit'] as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk();
        }
        foreach (['hero', 'history'] as $key) {
            $this->actingAs($admin)->get(route('admin.about.sections.edit', $key))->assertOk();
        }
        $this->actingAs($admin)->get('/admin/about/unknown')->assertNotFound();
    }

    public function test_demo_seeder_is_idempotent_flagged_and_renders_publicly(): void
    {
        Facility::create(['code' => 'H1', 'slug' => 'h1', 'district' => 'T', 'commune' => 'T', 'name_km' => 'Real hospital', 'status' => 'published', 'facility_type' => 'referral_hospital']);
        $this->seed(AboutDemoSeeder::class);
        $this->seed(AboutDemoSeeder::class);

        $this->assertSame(3, AboutSection::count());
        $this->assertSame(4, AboutItem::where('group', 'cards')->count());
        $this->assertSame(4, AboutItem::where('group', 'timeline')->count());
        $this->assertSame(4, FacilityLeader::department()->count());
        $this->assertSame(4, AboutOrgUnit::count());
        $this->assertSame(0, AboutItem::where('is_demo', false)->count());
        $this->assertTrue(AboutOrgUnit::where('name', 'Real hospital')->whereNotNull('facility_id')->exists());

        $this->get(route('about'))->assertOk()
            ->assertSee('ព្រឹត្តិការណ៍គំរូទី ១')->assertSee('ថ្នាក់ដឹកនាំទី ១')->assertSee('ការិយាល័យគំរូទី ១')
            ->assertSee(route('facilities.show', Facility::first()), false)
            ->assertSee('data-about-dropdown', false)->assertSee('data-about-carousel', false);
    }

    public function test_items_crud_toggle_soft_delete_restore_and_public_visibility(): void
    {
        $admin = $this->user();
        $this->actingAs($admin)->post(route('admin.about.items.store', 'timeline'), [
            'date_label' => '២០២០', 'title' => 'Milestone A', 'body' => '<b>Desc A</b>', 'is_active' => 1,
        ])->assertRedirect(route('admin.about.items.index', 'timeline'));
        $item = AboutItem::firstOrFail();
        $this->assertSame('Desc A', $item->body);
        $this->get(route('about'))->assertSee('Milestone A')->assertSee('id="timeline"', false);

        $this->actingAs($admin)->post(route('admin.about.items.toggle', ['timeline', $item]))->assertRedirect();
        $this->get(route('about'))->assertDontSee('Milestone A')->assertDontSee('id="timeline"', false);

        $this->actingAs($admin)->put(route('admin.about.items.update', ['timeline', $item]), ['date_label' => '២០២១', 'title' => 'Milestone B', 'is_active' => 1])->assertRedirect();
        $this->get(route('about'))->assertSee('Milestone B');

        $this->actingAs($admin)->delete(route('admin.about.items.destroy', ['timeline', $item]))->assertRedirect();
        $this->assertSoftDeleted($item);
        $this->get(route('about'))->assertDontSee('Milestone B');
        $this->actingAs($admin)->get(route('admin.about.items.index', ['timeline', 'trashed' => 1]))->assertSee('Milestone B');

        $this->actingAs($admin)->post(route('admin.about.items.restore', ['timeline', $item->id]))->assertRedirect();
        $this->get(route('about'))->assertSee('Milestone B');

        // Wrong group in the URL never reaches the record.
        $this->actingAs($admin)->get(route('admin.about.items.edit', ['cards', $item]))->assertNotFound();
        $this->assertTrue(AuditLog::where('entity_type', 'AboutItem')->where('action', 'restored')->exists());
    }

    public function test_hidden_or_deleted_managed_group_does_not_fall_back_to_legacy_copy(): void
    {
        $admin = $this->user();
        $this->get(route('about'))->assertSee(config('about.vision'));
        $this->actingAs($admin)->post(route('admin.about.items.store', 'cards'), ['title' => 'ចក្ខុវិស័យ', 'body' => 'Managed vision', 'icon_key' => 'eye', 'accent' => 'blue', 'is_active' => 1]);
        $card = AboutItem::firstOrFail();
        $this->get(route('about'))->assertSee('Managed vision')->assertSee('id="vision"', false)->assertDontSee(config('about.vision'));

        $this->actingAs($admin)->delete(route('admin.about.items.destroy', ['cards', $card]));
        $this->get(route('about'))->assertDontSee('Managed vision')->assertDontSee(config('about.vision'));
    }

    public function test_card_links_and_icons_are_whitelisted(): void
    {
        $admin = $this->user();
        $this->actingAs($admin)->post(route('admin.about.items.store', 'cards'), ['title' => 'X', 'icon_key' => 'eye', 'accent' => 'blue', 'link_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('link_url');
        $this->actingAs($admin)->post(route('admin.about.items.store', 'cards'), ['title' => 'X', 'icon_key' => '<svg onload=x>', 'accent' => 'blue'])
            ->assertSessionHasErrors('icon_key');
        $this->assertSame(0, AboutItem::count());
    }

    public function test_documents_use_media_library_or_published_legal_documents_and_keep_files_on_delete(): void
    {
        $admin = $this->user();
        Storage::fake('public');
        Storage::disk('public')->put('library/plan.pdf', str_repeat('x', 2048));
        $pdf = Media::create(['disk_path' => 'library/plan.pdf', 'original_filename' => 'plan.pdf', 'mime_type' => 'application/pdf', 'size' => 2048]);

        $this->actingAs($admin)->post(route('admin.about.items.store', 'documents'), ['title' => 'Strategic plan', 'source' => 'media', 'media_id' => $pdf->id, 'is_active' => 1])->assertRedirect();
        $this->get(route('about'))->assertSee('Strategic plan')->assertSee('2 KB')->assertSee('library/plan.pdf', false);

        $item = AboutItem::firstOrFail();
        $this->actingAs($admin)->delete(route('admin.about.items.destroy', ['documents', $item]));
        $this->actingAs($admin)->delete(route('admin.about.items.force-destroy', ['documents', $item->id]))->assertRedirect();
        $this->assertModelMissing($item);
        $this->assertNotNull($pdf->fresh());
        Storage::disk('public')->assertExists('library/plan.pdf');
        $this->get(route('about'))->assertDontSee('Strategic plan')->assertDontSee('about-files-title', false);
    }

    public function test_leaders_crud_uses_media_portrait_and_top_anchored_crop(): void
    {
        $admin = $this->user();
        $portrait = $this->image('library/leader.jpg');
        FacilityLeader::create(['facility_id' => null, 'name' => 'Existing leader', 'title' => 'Director']);

        $this->actingAs($admin)->post(route('admin.about.leaders.store'), [
            'name' => 'New leader', 'title' => 'Deputy', 'portrait_media_id' => $portrait->id, 'is_active' => 1, 'sort_order' => 0,
        ])->assertRedirect(route('admin.about.leaders.index'));
        $leader = FacilityLeader::where('name', 'New leader')->firstOrFail();
        $this->assertNull($leader->facility_id);

        $this->get(route('about'))->assertSee('New leader')->assertSee('library/leader.jpg', false)->assertSee('Existing leader');
        $this->assertStringContainsString('object-position: top center', file_get_contents(resource_path('css/about.css')));

        $this->actingAs($admin)->post(route('admin.about.leaders.toggle', $leader));
        $this->get(route('about'))->assertDontSee('New leader');

        $this->actingAs($admin)->delete(route('admin.about.leaders.destroy', $leader));
        $this->assertSoftDeleted($leader);
        $this->assertNotNull($portrait->fresh());

        // Facility-level leaders are not editable from the About screens.
        $hospital = Facility::create(['code' => 'H1', 'slug' => 'h1', 'district' => 'T', 'commune' => 'T', 'name_km' => 'H', 'status' => 'published', 'facility_type' => 'referral_hospital']);
        $facilityLeader = FacilityLeader::create(['facility_id' => $hospital->id, 'name' => 'Hospital head', 'title' => 'Director']);
        $this->actingAs($admin)->get(route('admin.about.leaders.edit', $facilityLeader))->assertNotFound();
    }

    public function test_organization_units_group_publicly_and_import_links_facilities_once(): void
    {
        $admin = $this->user();
        $hospital = Facility::create(['code' => 'H1', 'slug' => 'h1', 'district' => 'T', 'commune' => 'T', 'name_km' => 'Linked hospital', 'status' => 'published', 'facility_type' => 'referral_hospital']);

        $this->actingAs($admin)->post(route('admin.about.organization.import'))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.about.organization.import'));
        $this->assertSame(1, AboutOrgUnit::count());

        $this->actingAs($admin)->post(route('admin.about.organization.store'), ['name' => 'Planning office', 'type' => 'office', 'is_active' => 1])->assertRedirect();
        $parent = AboutOrgUnit::where('name', 'Planning office')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.about.organization.store'), ['name' => 'Data unit', 'type' => 'office', 'parent_id' => $parent->id, 'is_active' => 1]);

        $this->get(route('about'))->assertOk()->assertSee('Planning office')->assertSee('Data unit')->assertSee('id="offices"', false)
            ->assertSee(route('facilities.show', $hospital), false);

        $this->actingAs($admin)->post(route('admin.about.organization.toggle', $parent));
        $this->get(route('about'))->assertDontSee('Planning office')->assertSee('Data unit');
    }

    public function test_sections_update_hero_banner_sanitize_history_and_hide_when_inactive(): void
    {
        $admin = $this->user();
        $image = $this->image();

        $this->actingAs($admin)->put(route('admin.about.sections.update', 'hero'), ['title' => 'Custom about title', 'subtitle' => 'Custom subtitle', 'media_id' => $image->id, 'is_active' => 1])->assertRedirect();
        $this->assertSame($image->id, PageBanner::where('page_key', 'about')->value('media_id'));

        $this->actingAs($admin)->put(route('admin.about.sections.update', 'history'), [
            'title' => 'Our history', 'body' => '<p>Safe text</p><script>alert(1)</script>', 'image_position' => 'left', 'is_active' => 1,
        ])->assertRedirect();
        $this->assertStringNotContainsString('<script', AboutSection::where('key', 'history')->value('body'));

        $this->actingAs($admin)->put(route('admin.about.sections.update', 'mission'), ['title' => 'បេសកកម្ម', 'body' => 'Managed mission message', 'icon_key' => 'target', 'is_active' => 1])
            ->assertRedirect(route('admin.about.sections.edit', 'history'));

        $this->get(route('about'))->assertSee('Custom about title')->assertSee('Custom subtitle')->assertSee('library/about.jpg', false)
            ->assertSee('Safe text', false)->assertDontSee('alert(1)', false)->assertSee('is-image-left', false)->assertSee('Managed mission message');

        $this->actingAs($admin)->put(route('admin.about.sections.update', 'history'), ['title' => 'Our history', 'body' => '<p>Safe text</p>', 'image_position' => 'right']);
        $this->get(route('about'))->assertDontSee('Safe text', false)->assertDontSee('id="history"', false);
        $this->assertTrue(AuditLog::where('entity_type', 'AboutSection')->where('action', 'unpublished')->exists());
    }

    public function test_contact_and_map_reuse_site_settings_and_require_settings_permission(): void
    {
        $admin = $this->user('Website Admin');
        $this->get(route('about'))->assertDontSee('data-about-map ', false);

        $this->actingAs($admin)->put(route('admin.about.contact.update'), [
            'phone' => '012 345 678', 'email' => 'about@example.test', 'address' => 'Test address', 'office_hours' => 'Mon–Fri',
            'website_url' => 'https://example.test', 'map_latitude' => '13.5', 'map_longitude' => '105.9', 'map_zoom' => 16, 'map_url' => 'https://maps.example.test/x',
        ])->assertRedirect(route('admin.about.contact.edit'));
        $this->assertSame('012 345 678', Setting::get('phone'));

        // Settings drive config('site.*') at boot, as in the header/footer; re-run that boot step.
        (new \App\Providers\AppServiceProvider($this->app))->boot();
        $this->get(route('about'))->assertSee('tel:012345678', false)->assertSee('data-lat="13.5"', false)->assertSee('data-zoom="16"', false)
            ->assertSee('https://maps.example.test/x', false)->assertSee('example.test');

        $this->actingAs($admin)->put(route('admin.about.contact.update'), ['map_latitude' => '200', 'map_longitude' => '105'])->assertSessionHasErrors('map_latitude');

        $editor = $this->user('Editor');
        $editor->givePermissionTo('about.manage');
        $this->actingAs($editor)->get(route('admin.about.contact.edit'))->assertOk()->assertSee('ត្រូវការសិទ្ធិ');
        $this->actingAs($editor)->put(route('admin.about.contact.update'), ['phone' => '1'])->assertForbidden();
    }

    public function test_reorder_changes_public_order(): void
    {
        $admin = $this->user();
        $first = AboutItem::create(['group' => 'timeline', 'date_label' => '1', 'title' => 'Alpha event', 'sort_order' => 0]);
        $second = AboutItem::create(['group' => 'timeline', 'date_label' => '2', 'title' => 'Beta event', 'sort_order' => 1]);

        $this->actingAs($admin)->postJson(route('admin.about.items.reorder', 'timeline'), ['items' => [['id' => $second->id, 'sort_order' => 0], ['id' => $first->id, 'sort_order' => 1]]])->assertOk();
        $html = $this->get(route('about'))->getContent();
        $this->assertLessThan(strpos($html, 'Alpha event'), strpos($html, 'Beta event'));
    }
}
