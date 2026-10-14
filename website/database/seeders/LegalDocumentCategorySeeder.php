<?php

namespace Database\Seeders;

use App\Models\LegalDocumentCategory;
use Illuminate\Database\Seeder;

class LegalDocumentCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = ['ច្បាប់', 'ព្រះរាជក្រឹត្យ', 'អនុក្រឹត្យ', 'ប្រកាស', 'សេចក្តីសម្រេច', 'សារាចរ', 'សេចក្តីណែនាំ', 'គោលការណ៍ណែនាំ', 'SOP', 'ផ្សេងៗ'];

        foreach ($categories as $order => $name) {
            LegalDocumentCategory::create([
                'name' => $name,
                'slug' => \Illuminate\Support\Str::slug($name) ?: 'category-' . $order,
                'sort_order' => $order,
            ]);
        }
    }
}
