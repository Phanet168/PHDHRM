<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            NewsCategorySeeder::class,
            ContentCategorySeeder::class,
            LegalDocumentCategorySeeder::class,
            HealthContentCategorySeeder::class,
            FacilitySeeder::class,
            UserSeeder::class,
            FacilityLeaderSeeder::class,
            NewsSeeder::class,
            ContentSeeder::class,
            LegalDocumentSeeder::class,
            HealthContentSeeder::class,
            PartnerSeeder::class,
            HeroSlideSeeder::class,
            HomepageQuickLinkSeeder::class,
            HealthMessageSeeder::class,
            MenuSeeder::class,
            SettingSeeder::class,
        ]);

        // DEMO ABOUT CONTENT: placeholders for a fresh install, never seeded in production.
        if (! app()->isProduction()) {
            $this->call(AboutDemoSeeder::class);
        }
    }
}
