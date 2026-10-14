<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\FacilityLeader;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    private function block(Page $page, string $type, array $data, bool $active = true): PageBlock
    {
        return $page->blocks()->create(['block_type' => $type, 'data' => $data, 'is_active' => $active, 'sort_order' => $page->blocks()->count()]);
    }

    private function page(array $attributes = []): Page
    {
        return Page::create(array_merge(['title_km' => 'អំពីមន្ទីរ', 'slug' => 'about', 'template' => 'about', 'status' => 'published'], $attributes));
    }

    public function test_fallback_has_real_anchor_targets_and_no_empty_optional_sections(): void
    {
        $response = $this->get(route('about'))->assertOk();
        $response->assertSee(config('about.history'))
            ->assertSee('data-about-dropdown', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertDontSee('id="timeline"', false)
            ->assertDontSee('id="leadership"', false)
            ->assertDontSee('id="structure"', false)
            ->assertDontSee('data-about-map ', false)
            ->assertDontSee('about-files-title', false);
        preg_match_all('/data-about-link="([^"]+)"/', $response->getContent(), $matches);
        foreach ($matches[1] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $response->getContent());
        }
        $this->assertSame(1, substr_count($response->getContent(), '<h1 '));
    }

    public function test_only_published_page_and_active_blocks_are_used_without_creating_records(): void
    {
        $draft = $this->page(['status' => 'draft', 'content' => 'Private draft history']);
        $this->get(route('about'))->assertDontSee('Private draft history')->assertSee(config('about.history'));
        $draft->update(['status' => 'published']);
        $this->block($draft, 'text', ['html' => '<p>Published department history</p>']);
        $this->block($draft, 'text', ['html' => '<p>Hidden block</p>'], false);
        $this->get(route('about'))->assertOk()->assertSee('Published department history')->assertDontSee('Hidden block')->assertDontSee(config('about.history'));
        $this->assertDatabaseCount('pages', 1);
        $this->assertDatabaseCount('page_blocks', 2);
        $draft->delete();
        $this->get(route('about'))->assertOk()->assertDontSee('Published department history');
    }

    public function test_cms_media_core_cards_timeline_documents_and_zero_coordinate_map_are_reused(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('library/building.jpg', 'test image');
        Storage::disk('public')->put('library/chart.pdf', 'test PDF');
        $image = Media::create(['disk_path' => 'library/building.jpg', 'original_filename' => 'building.jpg', 'mime_type' => 'image/jpeg', 'size' => 10, 'alt_text' => 'CMS building alt']);
        $file = Media::create(['disk_path' => 'library/chart.pdf', 'original_filename' => 'chart.pdf', 'mime_type' => 'application/pdf', 'size' => 2048]);
        $page = $this->page();
        $this->block($page, 'image_text', ['html' => '<p>Real CMS history</p>', 'media_id' => $image->id, 'image' => 'obsolete/path.jpg']);
        $this->block($page, 'quote', ['text' => 'CMS mission quotation', 'author' => 'CMS attribution']);
        $this->block($page, 'cards', ['cards' => [
            ['title' => 'ចក្ខុវិស័យ', 'text' => 'CMS vision'],
            ['title' => 'បេសកកម្ម', 'text' => 'CMS mission'],
            ['title' => 'តួនាទី និងភារកិច្ច', 'text' => 'CMS roles'],
            ['title' => 'គោលតម្លៃ', 'text' => 'CMS values'],
            ['title' => 'Extra CMS card', 'text' => 'Keep this card'],
        ]]);
        $this->block($page, 'heading', ['text' => 'ប្រវត្តិ និងការអភិវឌ្ឍ', 'level' => 'h2']);
        $this->block($page, 'cards', ['cards' => [['title' => '2001 Test event', 'text' => 'Test event description']]]);
        $this->block($page, 'heading', ['text' => 'ឯកសារពាក់ព័ន្ធ', 'level' => 'h2']);
        $this->block($page, 'button', ['text' => 'CMS organization chart', 'url' => $file->url]);
        $this->block($page, 'map', ['lat' => 0, 'lng' => 0, 'zoom' => 8, 'marker_title' => 'Test-only location']);
        $response = $this->get(route('about'))->assertOk();
        $response->assertSee('library/building.jpg', false)->assertDontSee('obsolete/path.jpg', false)
            ->assertSee('CMS building alt')->assertSee('Real CMS history', false)
            ->assertSee('CMS mission quotation')->assertSee('CMS attribution')
            ->assertSee('CMS vision')->assertSee('CMS roles')->assertSee('CMS values')
            ->assertSee('Extra CMS card')->assertSee('Keep this card')
            ->assertSee('id="timeline"', false)->assertSee('Test event description')
            ->assertSee('CMS organization chart')->assertSee('2 KB')
            ->assertSee('data-lat="0"', false)->assertSee('data-lng="0"', false);
        $file->delete();
        $this->get(route('about'))->assertOk()->assertDontSee('class="about-file"', false);
    }

    public function test_department_leaders_and_published_organization_only_are_shown(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('leaders/director.jpg', 'test image');
        FacilityLeader::create(['name' => 'Department director', 'title' => 'Director', 'photo' => 'leaders/director.jpg']);
        $hospital = Facility::create(['code' => 'H1', 'slug' => 'test-hospital', 'district' => 'Test', 'commune' => 'Test', 'name_km' => 'Published hospital', 'status' => 'published', 'facility_type' => 'referral_hospital']);
        $office = Facility::create(['code' => 'OD1', 'slug' => 'test-od', 'district' => 'Test', 'commune' => 'Test', 'name_km' => 'Published OD', 'status' => 'published', 'facility_type' => 'operational_district_office']);
        Facility::create(['code' => 'H2', 'slug' => 'private-hospital', 'district' => 'Test', 'commune' => 'Test', 'name_km' => 'Private hospital', 'status' => 'draft', 'facility_type' => 'referral_hospital']);
        FacilityLeader::create(['facility_id' => $hospital->id, 'name' => 'Hospital-only leader', 'title' => 'Director']);
        $this->get(route('about'))->assertOk()->assertSee('Department director')->assertSee('leaders/director.jpg', false)
            ->assertSee('Published hospital')->assertSee(route('facilities.show', $office), false)
            ->assertDontSee('Private hospital')->assertDontSee('Hospital-only leader');
    }

    public function test_shared_contact_configuration_and_map_search_are_preserved(): void
    {
        config(['site.phone' => '123 456', 'site.email' => 'department@example.test', 'site.address' => 'Configured address', 'site.hours' => 'Configured office hours', 'site.facebook_url' => 'https://example.test/department']);
        $response = $this->get(route('about'))->assertOk();
        $response->assertSee('tel:123456', false)->assertSee('mailto:department@example.test', false)
            ->assertSee('Configured address')->assertSee('Configured office hours')
            ->assertSee('https://example.test/department', false)->assertSee('google.com/maps/search/?api=1', false);
        $this->assertGreaterThanOrEqual(3, substr_count($response->getContent(), 'department@example.test'));
    }
}

