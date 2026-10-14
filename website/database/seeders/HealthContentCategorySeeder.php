<?php

namespace Database\Seeders;

use App\Models\HealthContentCategory;
use Illuminate\Database\Seeder;

class HealthContentCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name_km' => 'សុខភាពមាតា', 'slug' => 'maternal-health', 'icon' => '♙'],
            ['name_km' => 'សុខភាពកុមារ', 'slug' => 'child-health', 'icon' => '☻'],
            ['name_km' => 'វ៉ាក់សាំង', 'slug' => 'vaccination', 'icon' => '✚'],
            ['name_km' => 'អាហារូបត្ថម្ភ', 'slug' => 'nutrition', 'icon' => '◉'],
            ['name_km' => 'គ្រុនឈាម', 'slug' => 'dengue', 'icon' => '◌'],
            ['name_km' => 'ជំងឺមិនឆ្លង', 'slug' => 'ncd', 'icon' => '♥'],
            ['name_km' => 'ផ្សេងៗ', 'slug' => 'other', 'icon' => '●'],
        ];

        foreach ($categories as $order => $category) {
            HealthContentCategory::create([...$category, 'sort_order' => $order]);
        }
    }
}
