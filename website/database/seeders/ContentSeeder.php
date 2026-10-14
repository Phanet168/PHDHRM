<?php

namespace Database\Seeders;

use App\Models\Content;
use App\Models\ContentCategory;
use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContentSeeder extends Seeder
{
    private array $stock = ['stock-1.jpg', 'stock-2.jpg', 'stock-3.jpg', 'stock-4.jpg'];

    public function run(): void
    {
        $author = User::role('Website Admin')->first() ?? User::first();
        $categories = ContentCategory::pluck('id', 'slug');

        $articles = [
            [
                'category' => 'department-news',
                'slug' => 'community-health-campaign-launch',
                'title' => 'មន្ទីរសុខាភិបាលខេត្តស្ទឹងត្រែងបើកយុទ្ធនាការសុខភាពសហគមន៍',
                'excerpt' => 'សកម្មភាពអប់រំសុខភាព និងផ្តល់សេវាពិគ្រោះជំងឺដោយឥតគិតថ្លៃដល់ប្រជាពលរដ្ឋក្នុងខេត្ត។',
            ],
            [
                'category' => 'leadership-activities',
                'slug' => 'leadership-field-inspection',
                'title' => 'ថ្នាក់ដឹកនាំមន្ទីរសុខាភិបាលចុះត្រួតពិនិត្យការងារនៅមូលដ្ឋាន',
                'excerpt' => 'ការចុះត្រួតពិនិត្យផ្ទាល់ដើម្បីធានាគុណភាពសេវាសុខាភិបាលនៅតាមស្រុកនានា។',
            ],
            [
                'category' => 'meetings',
                'slug' => 'annual-action-plan-review-meeting',
                'title' => 'កិច្ចប្រជុំពិនិត្យផែនការសកម្មភាពប្រចាំឆ្នាំ',
                'excerpt' => 'ការពិភាក្សាលទ្ធផលការងារឆ្នាំមុន និងទិសដៅសម្រាប់ឆ្នាំបន្ទាប់។',
            ],
            [
                'category' => 'trainings',
                'slug' => 'staff-technical-training-workshop',
                'title' => 'វគ្គបណ្តុះបណ្តាលបច្ចេកទេសសម្រាប់បុគ្គលិកសុខាភិបាល',
                'excerpt' => 'ការពង្រឹងសមត្ថភាពបុគ្គលិកលើបច្ចេកទេសព្យាបាល និងការថែទាំអ្នកជំងឺ។',
            ],
            [
                'category' => 'maternal-health',
                'slug' => 'maternal-health-pregnancy-care-guide',
                'title' => 'ការណែនាំអំពីការថែទាំសុខភាពមាតាកំឡុងពេលមានផ្ទៃពោះ',
                'excerpt' => 'ព័ត៌មានសំខាន់ៗសម្រាប់ស្ត្រីមានផ្ទៃពោះ ដើម្បីធានាសុវត្ថិភាពម្តាយ និងទារក។',
            ],
            [
                'category' => 'child-health',
                'slug' => 'child-health-care-under-five',
                'title' => 'របៀបថែទាំសុខភាពកុមារអាយុក្រោម ៥ ឆ្នាំ',
                'excerpt' => 'ការណែនាំអំពីអាហារូបត្ថម្ភ និងការការពារជំងឺទូទៅចំពោះកុមារតូច។',
            ],
            [
                'category' => 'vaccination',
                'slug' => 'child-vaccination-program',
                'title' => 'កម្មវិធីចាក់វ៉ាក់សាំងបង្ការជំងឺសម្រាប់កុមារ',
                'excerpt' => 'កាលវិភាគចាក់វ៉ាក់សាំងជាតិ និងអត្ថប្រយោជន៍នៃការចាក់វ៉ាក់សាំងទាន់ពេលវេលា។',
            ],
            [
                'category' => 'dengue-fever',
                'slug' => 'dengue-fever-prevention-rainy-season',
                'title' => 'វិធានការបង្ការជំងឺគ្រុនឈាមក្នុងរដូវវស្សា',
                'excerpt' => 'ការណែនាំអំពីការកម្ចាត់ប្រភពមេរោគ និងសញ្ញាព្រមានគ្រុនឈាមដែលត្រូវប្រុងប្រយ័ត្ន។',
            ],
        ];

        foreach ($articles as $i => $data) {
            $slug = $data['slug'];

            if (Content::where('slug', $slug)->exists()) {
                continue;
            }

            $content = Content::create([
                'category_id' => $categories[$data['category']] ?? null,
                'title_km' => $data['title'],
                'slug' => $slug,
                'excerpt' => $data['excerpt'],
                'status' => 'published',
                'is_featured' => $i < 2,
                'published_at' => now()->subDays($i),
                'author_id' => $author?->id,
            ]);

            $this->seedGallery($content);
            $this->seedBlocks($content, $data);
        }
    }

    private function seedGallery(Content $content): void
    {
        foreach ($this->stock as $j => $stockFile) {
            $sourcePath = public_path('images/stock/' . $stockFile);
            if (! file_exists($sourcePath)) {
                continue;
            }

            $filename = Str::uuid() . '.jpg';
            $diskPath = 'content/gallery/' . $filename;
            Storage::disk('public')->put($diskPath, file_get_contents($sourcePath));

            $media = Media::create([
                'original_filename' => $stockFile,
                'disk_path' => $diskPath,
                'mime_type' => 'image/jpeg',
                'size' => filesize($sourcePath),
                'collection' => 'content/gallery',
            ]);

            $content->images()->create([
                'media_id' => $media->id,
                'image' => $diskPath,
                'caption' => null,
                'is_featured' => $j === 0,
                'sort_order' => $j,
            ]);
        }
    }

    private function seedBlocks(Content $content, array $data): void
    {
        $blocks = [
            ['block_type' => 'heading', 'data' => ['text' => $data['title'], 'level' => 'h2']],
            ['block_type' => 'text', 'data' => ['html' => '<p>' . $data['excerpt'] . ' មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្តស្ទឹងត្រែងបន្តអនុវត្តសកម្មភាពនេះជាបន្តបន្ទាប់ ដើម្បីលើកកម្ពស់សុខភាព និងសុខុមាលភាពរបស់ប្រជាពលរដ្ឋក្នុងខេត្ត។</p>']],
            ['block_type' => 'quote', 'data' => ['text' => 'សុខភាពល្អគឺជាមូលដ្ឋានគ្រឹះនៃការអភិវឌ្ឍសហគមន៍។', 'author' => 'មន្ទីរសុខាភិបាលខេត្តស្ទឹងត្រែង']],
            ['block_type' => 'gallery', 'data' => ['image_ids' => []]],
            ['block_type' => 'button', 'data' => ['text' => 'ទាក់ទងមកកាន់មន្ទីរសុខាភិបាល', 'url' => '/contact', 'style' => 'primary', 'target' => '_self']],
        ];

        foreach ($blocks as $order => $block) {
            $content->blocks()->create([
                'block_type' => $block['block_type'],
                'data' => $block['data'],
                'is_active' => true,
                'sort_order' => $order,
            ]);
        }
    }
}
