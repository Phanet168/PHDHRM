<?php

namespace Database\Seeders;

use App\Models\HomepageQuickLink;
use Illuminate\Database\Seeder;

class HomepageQuickLinkSeeder extends Seeder
{
    public function run(): void
    {
        $links = [
            ['title_km' => 'មូលដ្ឋានសុខាភិបាល', 'description_km' => 'ស្វែងរកមន្ទីរពេទ្យ និងមណ្ឌលសុខភាពក្នុងខេត្ត។', 'icon' => 'pin', 'route_name' => 'facilities.index'],
            ['title_km' => 'ព័ត៌មាន និងសកម្មភាព', 'description_km' => 'តាមដានព័ត៌មាន និងសកម្មភាពសុខាភិបាលចុងក្រោយ។', 'icon' => 'categories', 'route_name' => 'news.index'],
            ['title_km' => 'លិខិតបទដ្ឋានគតិយុត្ត', 'description_km' => 'ស្វែងរក និងទាញយកលិខិតបទដ្ឋានគតិយុត្ត។', 'icon' => 'document', 'route_name' => 'legal-documents.index'],
            ['title_km' => 'មាតិកាអប់រំសុខភាព', 'description_km' => 'អានមាតិកាអប់រំសុខភាពសម្រាប់ប្រជាពលរដ្ឋ។', 'icon' => 'heart', 'route_name' => 'health-content.index'],
            ['title_km' => 'អំពីមន្ទីរ', 'description_km' => 'ស្វែងយល់អំពីមន្ទីរសុខាភិបាលខេត្តស្ទឹងត្រែង។', 'icon' => 'hospital', 'route_name' => 'about'],
            ['title_km' => 'ទំនាក់ទំនង', 'description_km' => 'ទំនាក់ទំនងមកកាន់មន្ទីរសុខាភិបាល។', 'icon' => 'phone', 'route_name' => 'contact'],
        ];

        foreach ($links as $index => $link) {
            HomepageQuickLink::create(array_merge($link, [
                'link_type' => 'route',
                'sort_order' => $index,
                'is_active' => true,
            ]));
        }

        // No HRM card here on purpose: HRM access already lives in the public
        // header, so a second entry point on this section is redundant.
    }
}
