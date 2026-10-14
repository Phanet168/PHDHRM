<?php

namespace App\Providers;

use App\Models\Setting;
use App\Support\SiteLogo;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function ($user, string $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });

        $this->overrideSiteConfigFromSettings();
    }

    private function overrideSiteConfigFromSettings(): void
    {
        // One cached settings snapshot per application boot; components only read site config.
        $settings = Schema::hasTable('settings') ? Setting::allSettings() : [];
        $defaultLogo = SiteLogo::url(config('site.default_logo') ?: config('site.logo'));
        $logo = $settings['logo'] ?? config('site.logo');
        // Browser-tab icon: a dedicated favicon upload wins if the admin set one, otherwise the
        // organization's own logo is used directly (per request — no separate upload required
        // just to get the real logo showing in the tab instead of the generic default).
        $favicon = $settings['favicon'] ?? $logo;

        config([
            'site.name_km' => $settings['org_name_km'] ?? config('site.name_km'),
            'site.name_en' => $settings['org_name_en'] ?? config('site.name_en'),
            'site.phone' => $settings['phone'] ?? config('site.phone'),
            'site.email' => $settings['email'] ?? config('site.email'),
            'site.address' => $settings['address'] ?? config('site.address'),
            'site.hours' => $settings['office_hours'] ?? config('site.hours'),
            'site.website' => $settings['website_url'] ?? config('site.website'),
            'site.map_url' => $settings['map_url'] ?? config('site.map_url'),
            'site.map_latitude' => $settings['map_latitude'] ?? config('site.map_latitude'),
            'site.map_longitude' => $settings['map_longitude'] ?? config('site.map_longitude'),
            'site.map_zoom' => $settings['map_zoom'] ?? config('site.map_zoom'),
            'site.logo' => $logo,
            'site.logo_url' => SiteLogo::url($logo) ?? $defaultLogo,
            'site.default_logo_url' => $defaultLogo,
            'site.favicon_url' => SiteLogo::url($favicon) ?? $defaultLogo,
            'site.loading_text_km' => $settings['loading_text_km'] ?? config('site.loading_text_km'),
            'site.facebook_url' => $settings['facebook_url'] ?? config('site.facebook_url'),
            'site.telegram_url' => $settings['telegram_url'] ?? config('site.telegram_url'),
            'site.youtube_url' => $settings['youtube_url'] ?? config('site.youtube_url'),
            'site.seo_title' => $settings['seo_title'] ?? config('site.name_km'),
            'site.seo_description' => $settings['seo_description'] ?? config('site.seo_description'),
            'site.hrm_url' => $settings['hrm_url'] ?? env('HRM_URL'),
        ]);
    }
}
