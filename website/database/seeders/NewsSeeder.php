<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NewsSeeder extends Seeder
{
    public function run(): void
    {
        $hospital = Facility::where('slug', 'referral-hospital-stung-treng')->first();
        $stock = ['stock-1.jpg', 'stock-2.jpg', 'stock-3.jpg', 'stock-4.jpg'];
        $categories = NewsCategory::pluck('id', 'slug');
        $author = User::role('Website Admin')->first() ?? User::first();

        $articles = [
            ['category' => 'hospital-news', 'title' => 'យុទ្ធនាការចាក់វ៉ាក់សាំង និងការអប់រំសុខភាពនៅសហគមន៍', 'facility' => null, 'status' => 'published'],
            ['category' => 'meeting', 'title' => 'កិច្ចប្រជុំថ្នាក់ដឹកនាំ និងត្រួតពិនិត្យការងារសុខាភិបាល', 'facility' => null, 'status' => 'published'],
            ['category' => 'training', 'title' => 'សកម្មភាពបណ្តុះបណ្តាល និងការពង្រឹងសមត្ថភាពបុគ្គលិក', 'facility' => null, 'status' => 'published'],
            ['category' => 'hospital-news', 'title' => 'សកម្មភាពសុខាភិបាល និងការចុះអប់រំប្រជាពលរដ្ឋនៅតាមមូលដ្ឋាន', 'facility' => null, 'status' => 'published'],
            ['category' => 'leadership', 'title' => 'ថ្នាក់ដឹកនាំចុះត្រួតពិនិត្យសេវាសុខាភិបាលនៅឃុំសេសាន', 'facility' => null, 'status' => 'published'],
            ['category' => 'meeting', 'title' => 'កិច្ចប្រជុំពិនិត្យផែនការសកម្មភាពប្រចាំត្រីមាស', 'facility' => null, 'status' => 'pending_review'],
            ['category' => 'hospital-news', 'title' => 'យុទ្ធនាការសុខភាព', 'facility' => $hospital?->id, 'status' => 'published'],
            ['category' => 'training', 'title' => 'សកម្មភាពបណ្តុះបណ្តាល', 'facility' => $hospital?->id, 'status' => 'draft'],
        ];

        foreach ($articles as $i => $article) {
            News::create([
                'category_id' => $categories[$article['category']] ?? null,
                'facility_id' => $article['facility'],
                'title_km' => $article['title'],
                'slug' => Str::slug($article['title']) . '-' . ($i + 1),
                'excerpt' => 'ព័ត៌មាន និងសកម្មភាពរបស់មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្តស្ទឹងត្រែង។',
                'content' => 'មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្តស្ទឹងត្រែងបន្តអនុវត្តសកម្មភាពសុខាភិបាល និងការផ្សព្វផ្សាយសុខភាពសម្រាប់ប្រជាពលរដ្ឋក្នុងខេត្តជាបន្តបន្ទាប់ ដើម្បីលើកកម្ពស់សុខភាព និងសុខុមាលភាពរបស់ប្រជាពលរដ្ឋ។',
                'featured_image' => 'images/stock/' . $stock[$i % count($stock)],
                'status' => $article['status'],
                'author_id' => $author?->id,
                'published_at' => $article['status'] === 'published' ? now()->subDays($i) : null,
            ]);
        }
    }
}
