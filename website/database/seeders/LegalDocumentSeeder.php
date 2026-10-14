<?php

namespace Database\Seeders;

use App\Models\LegalDocumentCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class LegalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $categories = LegalDocumentCategory::pluck('id')->all();
        $author = User::role('Website Admin')->first() ?? User::first();

        for ($i = 20; $i <= 26; $i++) {
            \App\Models\LegalDocument::create([
                'category_id' => $categories[$i % count($categories)],
                'title' => 'លិខិតស្តីពីការអនុវត្តការងារសុខាភិបាល និងការផ្តល់សេវាសាធារណៈ',
                'document_number' => 'លេខ ' . $i,
                'issuing_authority' => 'ក្រសួងសុខាភិបាល',
                'issue_date' => '2026-09-24',
                'year' => 2026,
                'status' => 'published',
                'published_at' => now(),
                'created_by' => $author?->id,
            ]);
        }
    }
}
