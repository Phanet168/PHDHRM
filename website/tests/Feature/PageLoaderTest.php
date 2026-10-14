<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\SiteLogo;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageLoaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_loader_header_and_footer_use_the_same_database_branding_once_per_page(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/organization.png', 'image fixture');
        Setting::set('org_name_km', 'ឈ្មោះស្ថាប័នពីទិន្នន័យ');
        Setting::set('org_name_en', 'Organization from database');
        Setting::set('logo', 'settings/organization.png');
        Setting::set('loading_text_km', 'សូមរង់ចាំបន្តិច');
        $this->app->getProvider(AppServiceProvider::class)->boot();

        $html = $this->get('/news')->assertOk()
            ->assertSee('ឈ្មោះស្ថាប័នពីទិន្នន័យ')->assertSee('Organization from database')
            ->assertSee('សូមរង់ចាំបន្តិច')->getContent();
        $this->assertSame(1, substr_count($html, 'id="page-loader"'));
        $this->assertSame(3, substr_count($html, 'src="'.e(asset('storage/settings/organization.png')).'"'));
    }

    public function test_logo_paths_support_disk_storage_and_public_assets_without_duplicate_prefixes(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/logo.png', 'image fixture');
        foreach (['settings/logo.png', 'storage/settings/logo.png', '/storage/settings/logo.png', 'public/storage/settings/logo.png', 'public/settings/logo.png'] as $path) {
            $this->assertSame(asset('storage/settings/logo.png'), SiteLogo::url($path));
        }
        $this->assertSame(asset('images/stock/stock-1.jpg'), SiteLogo::url('images/stock/stock-1.jpg'));
        $this->assertSame('https://example.org/logo.png', SiteLogo::url('https://example.org/logo.png'));
        $this->assertNull(SiteLogo::url('javascript:alert(1)'));
        $this->assertNull(SiteLogo::url('../private/logo.png'));
        $this->assertNull(SiteLogo::url('settings/missing.png'));
    }

    public function test_missing_logo_uses_configured_default_or_existing_header_mark(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/default.png', 'image fixture');
        config(['site.default_logo' => 'settings/default.png']);
        Setting::set('logo', 'settings/missing.png');
        $this->app->getProvider(AppServiceProvider::class)->boot();
        $this->assertSame(asset('storage/settings/default.png'), config('site.logo_url'));

        config(['site.default_logo' => null, 'site.logo' => null]);
        Setting::set('logo', null);
        $this->app->getProvider(AppServiceProvider::class)->boot();
        $html = Blade::render('<x-page-loader />');
        $this->assertStringContainsString('class="site-logo-fallback"', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('class="page-loader-text"', $html);
    }

    public function test_remote_logo_keeps_a_configured_fallback_and_settings_are_escaped(): void
    {
        config(['site.default_logo' => 'images/stock/stock-1.jpg']);
        Setting::set('logo', 'https://example.org/unavailable.png');
        Setting::set('loading_text_km', '<script>unsafe()</script>');
        $this->app->getProvider(AppServiceProvider::class)->boot();
        $html = Blade::render('<x-page-loader />');
        $this->assertStringContainsString('data-fallback="'.e(asset('images/stock/stock-1.jpg')).'"', $html);
        $this->assertStringContainsString('&lt;script&gt;unsafe()&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>unsafe()', $html);
    }

    public function test_favicon_falls_back_to_the_site_logo_when_no_favicon_is_uploaded(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/organization-logo.png', 'image fixture');
        Setting::set('logo', 'settings/organization-logo.png');
        Setting::set('favicon', null);
        $this->app->getProvider(AppServiceProvider::class)->boot();

        $this->assertSame(asset('storage/settings/organization-logo.png'), config('site.favicon_url'));

        $html = $this->get('/news')->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="icon" href="'.e(asset('storage/settings/organization-logo.png')).'">', $html);
    }

    public function test_a_dedicated_favicon_upload_takes_priority_over_the_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/organization-logo.png', 'logo fixture');
        Storage::disk('public')->put('settings/tab-icon.png', 'favicon fixture');
        Setting::set('logo', 'settings/organization-logo.png');
        Setting::set('favicon', 'settings/tab-icon.png');
        $this->app->getProvider(AppServiceProvider::class)->boot();

        $this->assertSame(asset('storage/settings/tab-icon.png'), config('site.favicon_url'));
    }

    public function test_the_admin_layout_and_login_page_also_render_the_favicon(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/tab-icon.png', 'favicon fixture');
        Setting::set('favicon', 'settings/tab-icon.png');
        $this->app->getProvider(AppServiceProvider::class)->boot();
        $expected = '<link rel="icon" href="'.e(asset('storage/settings/tab-icon.png')).'">';

        $this->get(route('admin.login'))->assertOk()->assertSee($expected, false);

        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee($expected, false);
    }

    public function test_settings_are_loaded_as_one_cached_snapshot_and_not_queried_by_components(): void
    {
        Setting::set('loading_text_km', 'First loading message');
        Cache::forget('settings');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $provider = $this->app->getProvider(AppServiceProvider::class);
        $provider->boot();
        $provider->boot();
        $settingsQueries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from "settings"'));
        $this->assertCount(1, $settingsQueries);

        DB::flushQueryLog();
        $html = Blade::render('<x-page-loader /><x-page-loader /><x-organization-logo />');
        $this->assertSame(1, substr_count($html, 'id="page-loader"'));
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();

        Setting::set('loading_text_km', 'Updated loading message');
        $provider->boot();
        $this->assertSame('Updated loading message', config('site.loading_text_km'));
    }
}
