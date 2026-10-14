<?php

namespace Database\Seeders;

use App\Models\HeroSlide;
use Illuminate\Database\Seeder;

class HeroSlideSeeder extends Seeder
{
    public function run(): void
    {
        HeroSlide::create([
            'image' => 'hero-slides/default-slide-1.jpg',
            'title_km' => 'មន្ទីរសុខាភិបាល ដើម្បីសុខភាពប្រជាពលរដ្ឋ',
            'title_en' => 'Provincial Health Department',
            'description' => 'ព័ត៌មានសុខាភិបាល មូលដ្ឋានសុខាភិបាល និងការផ្សព្វផ្សាយសុខភាពសម្រាប់ប្រជាពលរដ្ឋក្នុងខេត្តស្ទឹងត្រែង។',
            'button_text' => 'ស្វែងរកមូលដ្ឋាន',
            'button_link' => '/facilities',
            'sort_order' => 0,
            'is_active' => true,
            'published_at' => now(),
        ]);

        HeroSlide::create([
            'image' => 'hero-slides/default-slide-2.jpg',
            'title_km' => 'ព័ត៌មាន និងសកម្មភាពថ្មីៗ',
            'title_en' => 'Latest News & Activities',
            'description' => 'តាមដានព័ត៌មាន និងសកម្មភាពសុខាភិបាលចុងក្រោយបំផុតរបស់មន្ទីរសុខាភិបាលខេត្តស្ទឹងត្រែង។',
            'button_text' => 'ព័ត៌មានថ្មីៗ',
            'button_link' => '/news',
            'sort_order' => 1,
            'is_active' => true,
            'published_at' => now(),
        ]);
    }
}
