<?php

namespace Tests\Feature;

use App\Models\LegalDocument;
use App\Models\LegalDocumentCategory;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegalDocumentsPageTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $name): LegalDocumentCategory
    {
        return LegalDocumentCategory::create(['name' => $name, 'slug' => 'c'.uniqid(), 'sort_order' => 0]);
    }

    private function document(array $attributes = []): LegalDocument
    {
        static $n = 0;
        $n++;

        return LegalDocument::create(array_merge([
            'title' => 'លិខិតសាកល្បងលេខ '.$n,
            'document_number' => 'DOC-'.$n,
            'issuing_authority' => 'ក្រសួងសុខាភិបាល',
            'issue_date' => '2026-03-01',
            'year' => 2026,
            'status' => 'published',
            'published_at' => now(),
        ], $attributes));
    }

    // --- Structure ---

    public function test_page_renders_the_new_body_structure(): void
    {
        $this->document();

        $this->get(route('legal-documents.index'))
            ->assertOk()
            ->assertSee('legal-titlebar', false)
            ->assertSee('legal-search-card', false)
            ->assertSee('legal-doc-list', false)
            ->assertSee('legal-category-card', false)
            ->assertSee('ប្រភេទឯកសារ')
            ->assertSee('លទ្ធផលស្វែងរក')
            // The old admin-style table is gone.
            ->assertDontSee('legal-table', false)
            ->assertDontSee('<table', false);
    }

    public function test_filter_form_exposes_type_source_year_and_keyword(): void
    {
        $category = $this->category('ប្រកាស');
        $this->document(['category_id' => $category->id, 'issuing_authority' => 'អង្គភាពសាកល្បង']);

        $this->get(route('legal-documents.index'))
            ->assertOk()
            ->assertSee('name="type"', false)
            ->assertSee('name="issuing_body"', false)
            ->assertSee('name="year"', false)
            ->assertSee('name="keyword"', false)
            ->assertSee('<option value="'.$category->id.'"', false)
            ->assertSee('<option value="អង្គភាពសាកល្បង"', false);
    }

    public function test_document_card_shows_badges_number_date_and_download(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('legal-documents/x.pdf', str_repeat('a', 2048));
        $category = $this->category('អនុក្រឹត្យ');
        $document = $this->document([
            'category_id' => $category->id,
            'title' => 'ចំណងជើងសាកល្បងអនុក្រឹត្យ',
            'document_number' => '99/26',
            'issue_date' => '2026-09-24',
            'description' => '<p>ការពិពណ៌នា <b>សង្ខេប</b> សាកល្បង</p>',
            'file_path' => 'legal-documents/x.pdf',
        ]);

        $response = $this->get(route('legal-documents.index'));

        $response->assertOk()
            ->assertSee('ចំណងជើងសាកល្បងអនុក្រឹត្យ')
            ->assertSee('លេខឯកសារ៖ 99/26')
            ->assertSee('24-09-2026')
            ->assertSee('PDF · 2 KB')
            ->assertSee('ឆ្នាំ ២០២៦')
            ->assertSee('ការពិពណ៌នា សង្ខេប សាកល្បង')
            ->assertDontSee('<b>សង្ខេប</b>', false)
            ->assertSee('is-pdf', false)
            ->assertSee(route('legal-documents.download', $document), false);
    }

    public function test_document_without_a_file_has_no_download_link(): void
    {
        $document = $this->document(['file_path' => null]);

        $this->get(route('legal-documents.index'))
            ->assertOk()
            ->assertSee('មិនទាន់មានឯកសារ')
            ->assertDontSee(route('legal-documents.download', $document), false);
    }

    // --- Filters (query logic is unchanged) ---

    public function test_type_filter_narrows_results_and_marks_the_category_active(): void
    {
        $a = $this->category('ច្បាប់');
        $b = $this->category('ប្រកាស');
        $this->document(['category_id' => $a->id, 'title' => 'ឯកសារប្រភេទច្បាប់']);
        $this->document(['category_id' => $b->id, 'title' => 'ឯកសារប្រភេទប្រកាស']);

        $this->get(route('legal-documents.index', ['type' => $a->id]))
            ->assertOk()
            ->assertSee('ឯកសារប្រភេទច្បាប់')
            ->assertDontSee('ឯកសារប្រភេទប្រកាស')
            ->assertSee('aria-current="page"', false)
            ->assertSee('<option value="'.$a->id.'" selected', false);
    }

    public function test_source_year_and_keyword_filters_combine(): void
    {
        $this->document(['title' => 'ឯកសារមួយ', 'issuing_authority' => 'អង្គភាពក', 'year' => 2025]);
        $this->document(['title' => 'ឯកសារពីរ', 'issuing_authority' => 'អង្គភាពក', 'year' => 2026]);
        $this->document(['title' => 'ឯកសារបី', 'issuing_authority' => 'អង្គភាពខ', 'year' => 2026]);

        $this->get(route('legal-documents.index', ['issuing_body' => 'អង្គភាពក']))
            ->assertSee('ឯកសារមួយ')->assertSee('ឯកសារពីរ')->assertDontSee('ឯកសារបី');

        $this->get(route('legal-documents.index', ['issuing_body' => 'អង្គភាពក', 'year' => 2026]))
            ->assertDontSee('ឯកសារមួយ')->assertSee('ឯកសារពីរ')->assertDontSee('ឯកសារបី');

        $this->get(route('legal-documents.index', ['keyword' => 'ឯកសារបី']))
            ->assertDontSee('ឯកសារមួយ')->assertDontSee('ឯកសារពីរ')->assertSee('ឯកសារបី');
    }

    public function test_empty_result_shows_the_empty_state_instead_of_a_list(): void
    {
        $this->document(['title' => 'ឯកសារដែលមាន']);

        $this->get(route('legal-documents.index', ['keyword' => 'មិនមានទេ']))
            ->assertOk()
            ->assertSee('មិនមានឯកសារដែលត្រូវនឹងការស្វែងរកទេ')
            ->assertSee('legal-empty', false)
            ->assertDontSee('legal-doc-list', false);
    }

    public function test_invalid_filters_are_still_rejected(): void
    {
        $this->get(route('legal-documents.index', ['year' => 'abc']))->assertSessionHasErrors('year');
    }

    // --- Categories & counts ---

    public function test_category_counts_are_real_and_only_count_published_documents(): void
    {
        $category = $this->category('សារាចរ');
        $this->document(['category_id' => $category->id]);
        $this->document(['category_id' => $category->id]);
        $this->document(['category_id' => $category->id, 'status' => 'draft']);
        $this->document(['category_id' => $category->id, 'status' => 'archived']);

        $response = $this->get(route('legal-documents.index'));

        $response->assertOk();
        $this->assertMatchesRegularExpression('/សារាចរ<\/span>\s*<span class="legal-category-count">២<\/span>/u', $response->getContent());
        $this->assertMatchesRegularExpression('/ទាំងអស់<\/span>\s*<span class="legal-category-count">២<\/span>/u', $response->getContent());
        $response->assertSee('(២ ឯកសារ)');
    }

    public function test_category_links_keep_the_other_active_filters(): void
    {
        $category = $this->category('ព្រះរាជក្រឹត្យ');
        $this->document(['category_id' => $category->id]);

        $html = $this->get(route('legal-documents.index', ['year' => 2026, 'keyword' => 'x']))->getContent();

        $this->assertStringContainsString('type='.$category->id, $html);
        $this->assertMatchesRegularExpression('/href="[^"]*year=2026[^"]*type='.$category->id.'[^"]*#legal-results"/', $html);
    }

    // --- Pagination & no duplicates ---

    public function test_pagination_shows_ten_per_page_without_duplicates(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->document(['title' => sprintf('ឯកសារលំដាប់ %02d', $i), 'issue_date' => now()->subDays($i)->toDateString()]);
        }

        $page1 = $this->get(route('legal-documents.index'));
        $page1->assertOk()
            ->assertSee('បង្ហាញ ១-១០ នៃ ១២ ឯកសារ')
            ->assertSee('ទំព័រ ១ នៃ ២')
            ->assertSee('legal-pagination-links', false)
            ->assertSee('rel="next"', false);

        $page2 = $this->get(route('legal-documents.index', ['page' => 2]));
        $page2->assertOk()->assertSee('បង្ហាញ ១១-១២ នៃ ១២ ឯកសារ');

        preg_match_all('/ឯកសារលំដាប់ \d\d/u', $page1->getContent(), $first);
        preg_match_all('/ឯកសារលំដាប់ \d\d/u', $page2->getContent(), $second);
        $this->assertCount(10, array_unique($first[0]));
        $this->assertCount(2, array_unique($second[0]));
        $this->assertEmpty(array_intersect(array_unique($first[0]), array_unique($second[0])));
    }

    public function test_pagination_links_are_hidden_when_everything_fits_on_one_page(): void
    {
        $this->document();

        $this->get(route('legal-documents.index'))
            ->assertOk()
            ->assertSee('បង្ហាញ ១-១ នៃ ១ ឯកសារ')
            ->assertDontSee('legal-pagination-links', false);
    }

    // --- Download ---

    public function test_published_document_downloads_and_draft_or_missing_files_404(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('legal-documents/ok.pdf', 'pdf-bytes');

        $published = $this->document(['file_path' => 'legal-documents/ok.pdf']);
        $draft = $this->document(['file_path' => 'legal-documents/ok.pdf', 'status' => 'draft']);
        $missing = $this->document(['file_path' => 'legal-documents/gone.pdf']);

        $this->get(route('legal-documents.download', $published))->assertOk()->assertDownload();
        $this->get(route('legal-documents.download', $draft))->assertNotFound();
        $this->get(route('legal-documents.download', $missing))->assertNotFound();
    }

    // --- Documents that link to an externally hosted file (e.g. shared Google Drive) ---

    private function externalDocument(array $attributes = []): LegalDocument
    {
        return $this->document(array_merge([
            'title' => 'ឯកសារតំណខាងក្រៅ',
            'document_number' => null,
            'issue_date' => null,
            'year' => null,
            'external_url' => 'https://drive.google.com/uc?export=download&id=abc123',
            'external_format' => 'pdf',
        ], $attributes));
    }

    public function test_external_document_links_out_and_hides_unknown_number_date_and_year(): void
    {
        $document = $this->externalDocument();

        $response = $this->get(route('legal-documents.index'));

        $response->assertOk()
            ->assertSee('ឯកសារតំណខាងក្រៅ')
            ->assertSee('is-pdf', false)
            ->assertDontSee('មិនទាន់មានឯកសារ')
            ->assertDontSee('legal-badge is-year', false)
            ->assertDontSee('លេខឯកសារ៖')
            ->assertDontSee('name="calendar"', false);
        // Both the title and the button open the external file in a new tab, safely.
        $this->assertSame(2, preg_match_all('/<a href="https:\/\/drive\.google\.com\/uc\?export=download&amp;id=abc123" target="_blank" rel="noopener noreferrer"/', $response->getContent()));
        $response->assertDontSee(route('legal-documents.download', $document), false);
    }

    public function test_external_document_download_route_redirects_and_drafts_stay_hidden(): void
    {
        $published = $this->externalDocument();
        $draft = $this->externalDocument(['status' => 'draft', 'external_url' => 'https://drive.google.com/uc?export=download&id=draft1']);

        $this->get(route('legal-documents.download', $published))
            ->assertRedirect('https://drive.google.com/uc?export=download&id=abc123');
        $this->get(route('legal-documents.download', $draft))->assertNotFound();
    }

    public function test_uploaded_file_wins_over_an_external_link(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('legal-documents/own.pdf', 'x');
        $document = $this->externalDocument(['file_path' => 'legal-documents/own.pdf']);

        $this->get(route('legal-documents.index'))
            ->assertSee(route('legal-documents.download', $document), false)
            ->assertDontSee('drive.google.com', false);
        $this->get(route('legal-documents.download', $document))->assertOk()->assertDownload();
    }

    public function test_documents_without_a_date_paginate_without_duplicates_or_gaps(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->externalDocument(['title' => sprintf('ឯកសារគ្មានកាលបរិច្ឆេទ %02d', $i), 'year' => $i % 3 === 0 ? 2001 : null, 'external_url' => 'https://drive.google.com/uc?id=n'.$i]);
        }

        $seen = [];
        foreach ([1, 2, 3] as $page) {
            preg_match_all('/ឯកសារគ្មានកាលបរិច្ឆេទ \d\d/u', $this->get(route('legal-documents.index', ['page' => $page]))->getContent(), $found);
            $seen = array_merge($seen, array_unique($found[0]));
        }

        $this->assertCount(25, $seen);
        $this->assertCount(25, array_unique($seen));
    }

    public function test_year_filter_lists_only_known_years(): void
    {
        $this->document(['year' => 2020]);
        $this->externalDocument();

        $html = $this->get(route('legal-documents.index'))->assertOk()->getContent();
        preg_match('/<select id="legal-year".*?<\/select>/s', $html, $select);

        $this->assertSame(2, substr_count($select[0], '<option'));
        $this->assertStringContainsString('<option value="2020"', $select[0]);
    }

    // --- Useful links (built only from configured data) ---

    public function test_useful_links_card_is_hidden_when_nothing_is_configured(): void
    {
        $this->document();

        $this->get(route('legal-documents.index'))
            ->assertOk()
            ->assertDontSee('legal-links-card', false)
            ->assertDontSee('legal-links-heading', false);
    }

    public function test_useful_links_use_partner_websites_and_ignore_unsafe_or_missing_ones(): void
    {
        Partner::create(['name' => 'ដៃគូមានគេហទំព័រ', 'website' => 'https://partner.example.org', 'active' => true, 'sort_order' => 1]);
        Partner::create(['name' => 'ដៃគូគ្មានគេហទំព័រ', 'website' => null, 'active' => true, 'sort_order' => 2]);
        Partner::create(['name' => 'ដៃគូមិនសកម្ម', 'website' => 'https://inactive.example.org', 'active' => false, 'sort_order' => 3]);
        Partner::create(['name' => 'ដៃគូមិនសុវត្ថិភាព', 'website' => 'javascript:alert(1)', 'active' => true, 'sort_order' => 4]);

        $response = $this->get(route('legal-documents.index'));

        $response->assertOk()
            ->assertSee('legal-links-heading', false)
            ->assertDontSee('inactive.example.org', false)
            ->assertDontSee('javascript:alert', false);
        $this->assertStringNotContainsString('ដៃគូគ្មានគេហទំព័រ', $response->getContent());
        // External partner links open safely in a new tab.
        $this->assertMatchesRegularExpression('/<a href="https:\/\/partner\.example\.org"\s+target="_blank" rel="noopener noreferrer"/', $response->getContent());
    }

    public function test_useful_links_fall_back_to_the_configured_footer_menu(): void
    {
        $menu = Menu::create(['name' => 'Footer', 'slug' => 'footer', 'is_active' => true]);
        MenuItem::create(['menu_id' => $menu->id, 'label_km' => 'តំណរហ័សសាកល្បង', 'link_type' => 'url', 'url' => 'https://moh.example.org', 'target' => '_blank', 'is_active' => true, 'sort_order' => 0]);
        MenuItem::create(['menu_id' => $menu->id, 'label_km' => 'តំណគ្មានទីតាំង', 'link_type' => 'url', 'url' => null, 'is_active' => true, 'sort_order' => 1]);
        Menu::forgetCache();

        $html = $this->get(route('legal-documents.index'))->assertOk()->assertSee('legal-links-card', false)->getContent();

        $card = substr($html, strpos($html, 'legal-links-card'));
        $card = substr($card, 0, strpos($card, '</section>'));
        $this->assertStringContainsString('តំណរហ័សសាកល្បង', $card);
        $this->assertStringContainsString('rel="noopener noreferrer"', $card);
        $this->assertStringNotContainsString('តំណគ្មានទីតាំង', $card);
    }

    // --- Shared chrome is untouched ---

    public function test_shared_header_and_footer_are_still_rendered(): void
    {
        $this->get(route('legal-documents.index'))
            ->assertOk()
            ->assertSee('public-header', false)
            ->assertSee('public-footer', false)
            ->assertSee('<nav aria-label="breadcrumb"', false);
    }
}
