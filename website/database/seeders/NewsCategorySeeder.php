<?php

namespace Database\Seeders;

use App\Models\NewsCategory;
use Illuminate\Database\Seeder;

class NewsCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name_km' => 'ព័ត៌មានមន្ទីរ', 'slug' => 'hospital-news'],
            ['name_km' => 'សកម្មភាពថ្នាក់ដឹកនាំ', 'slug' => 'leadership'],
            ['name_km' => 'កិច្ចប្រជុំ', 'slug' => 'meeting'],
            ['name_km' => 'បណ្តុះបណ្តាល', 'slug' => 'training'],
            ['name_km' => 'យុទ្ធនាការសុខភាព', 'slug' => 'health-campaigns'],
            ['name_km' => 'សកម្មភាពមូលដ្ឋាន', 'slug' => 'local-activities'],
            ['name_km' => 'សេចក្តីជូនដំណឹង', 'slug' => 'announcements'],
        ];

        foreach ($categories as $order => $category) {
            NewsCategory::firstOrCreate(['slug' => $category['slug']], [...$category, 'sort_order' => $order]);
        }
    }
}
