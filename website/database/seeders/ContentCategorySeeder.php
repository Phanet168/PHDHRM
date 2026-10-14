<?php

namespace Database\Seeders;

use App\Models\ContentCategory;
use Illuminate\Database\Seeder;

class ContentCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name_km' => 'ព័ត៌មានមន្ទីរ', 'slug' => 'department-news'],
            ['name_km' => 'សកម្មភាពថ្នាក់ដឹកនាំ', 'slug' => 'leadership-activities'],
            ['name_km' => 'កិច្ចប្រជុំ', 'slug' => 'meetings'],
            ['name_km' => 'បណ្តុះបណ្តាល', 'slug' => 'trainings'],
            ['name_km' => 'សុខភាពមាតា', 'slug' => 'maternal-health'],
            ['name_km' => 'សុខភាពកុមារ', 'slug' => 'child-health'],
            ['name_km' => 'វ៉ាក់សាំង', 'slug' => 'vaccination'],
            ['name_km' => 'គ្រុនឈាម', 'slug' => 'dengue-fever'],
        ];

        foreach ($categories as $order => $category) {
            ContentCategory::firstOrCreate(
                ['slug' => $category['slug']],
                ['name_km' => $category['name_km'], 'is_active' => true, 'sort_order' => $order]
            );
        }
    }
}
