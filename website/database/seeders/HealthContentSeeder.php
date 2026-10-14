<?php

namespace Database\Seeders;

use App\Models\HealthContent;
use App\Models\HealthContentCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class HealthContentSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::role('Website Admin')->first() ?? User::first();

        foreach (HealthContentCategory::all() as $category) {
            $title = 'ការណែនាំអំពី' . $category->name_km;
            HealthContent::create([
                'category_id' => $category->id,
                'title_km' => $title,
                'slug' => Str::slug($category->slug),
                'excerpt' => 'ព័ត៌មានអប់រំសុខភាពស្តីពី' . $category->name_km . ' សម្រាប់ប្រជាពលរដ្ឋ។',
                'content' => 'មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្តស្ទឹងត្រែងផ្តល់ព័ត៌មានអប់រំសុខភាពដើម្បីជួយប្រជាពលរដ្ឋការពារ និងថែទាំសុខភាពខ្លួនប្រកបដោយប្រសិទ្ធភាព។',
                'status' => 'published',
                'author_id' => $author?->id,
                'published_at' => now(),
            ]);
        }
    }
}
