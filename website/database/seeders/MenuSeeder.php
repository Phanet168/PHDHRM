<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $navItems = [
            ['label_km' => 'ទំព័រដើម', 'label_en' => 'Home', 'route_name' => 'home'],
            ['label_km' => 'មូលដ្ឋានសុខាភិបាល', 'label_en' => 'Facilities', 'route_name' => 'facilities.index'],
            ['label_km' => 'ព័ត៌មាន និងសកម្មភាព', 'label_en' => 'News', 'route_name' => 'news.index'],
            ['label_km' => 'លិខិតបទដ្ឋានគតិយុត្ត', 'label_en' => 'Legal Documents', 'route_name' => 'legal-documents.index'],
            ['label_km' => 'អំពីមន្ទីរ', 'label_en' => 'About', 'route_name' => 'about'],
            ['label_km' => 'ទំនាក់ទំនង', 'label_en' => 'Contact', 'route_name' => 'contact'],
        ];

        $header = Menu::create(['name' => 'Header Menu', 'slug' => 'header', 'is_active' => true]);
        foreach ($navItems as $order => $navItem) {
            $header->items()->create([
                ...$navItem,
                'link_type' => 'route',
                'target' => '_self',
                'is_active' => true,
                'sort_order' => $order,
            ]);
        }

        $mobile = Menu::create(['name' => 'Mobile Menu', 'slug' => 'mobile', 'is_active' => true]);
        foreach ($navItems as $order => $navItem) {
            $mobile->items()->create([
                ...$navItem,
                'link_type' => 'route',
                'target' => '_self',
                'is_active' => true,
                'sort_order' => $order,
            ]);
        }

        $footerItems = [
            ['label_km' => 'មូលដ្ឋានសុខាភិបាល', 'label_en' => 'Facilities', 'route_name' => 'facilities.index'],
            ['label_km' => 'ព័ត៌មាន និងសកម្មភាព', 'label_en' => 'News', 'route_name' => 'news.index'],
            ['label_km' => 'អំពីមន្ទីរ', 'label_en' => 'About', 'route_name' => 'about'],
        ];

        $footer = Menu::create(['name' => 'Footer Menu', 'slug' => 'footer', 'is_active' => true]);
        foreach ($footerItems as $order => $item) {
            $footer->items()->create([
                ...$item,
                'link_type' => 'route',
                'target' => '_self',
                'is_active' => true,
                'sort_order' => $order,
            ]);
        }
    }
}
