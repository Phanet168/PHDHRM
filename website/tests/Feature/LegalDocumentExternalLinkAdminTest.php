<?php

namespace Tests\Feature;

use App\Models\LegalDocument;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalDocumentExternalLinkAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'ឯកសារតំណខាងក្រៅ',
            'document_number' => '001/26',
            'issuing_authority' => 'ក្រសួងសុខាភិបាល',
            'issue_date' => '2026-01-01',
            'year' => 2026,
            'external_url' => 'https://drive.google.com/uc?export=download&id=abc',
        ], $overrides);
    }

    public function test_a_document_can_be_created_from_an_external_link_without_uploading_a_file(): void
    {
        $this->actingAs($this->admin())->post(route('admin.legal-documents.store'), $this->payload())->assertSessionHasNoErrors();

        $document = LegalDocument::firstOrFail();
        $this->assertSame('https://drive.google.com/uc?export=download&id=abc', $document->external_url);
        $this->assertNull($document->file_path);
        $this->assertSame('draft', $document->status);
    }

    public function test_a_new_document_still_needs_either_a_file_or_a_link(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.legal-documents.store'), $this->payload(['external_url' => null]))
            ->assertSessionHasErrors('file');

        $this->assertSame(0, LegalDocument::count());
    }

    public function test_only_http_and_https_links_are_accepted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.legal-documents.store'), $this->payload(['external_url' => 'javascript:alert(1)']))
            ->assertSessionHasErrors('external_url');
        $this->actingAs($admin)->post(route('admin.legal-documents.store'), $this->payload(['external_url' => 'ftp://example.org/a.pdf']))
            ->assertSessionHasErrors('external_url');
        $this->assertSame(0, LegalDocument::count());
    }

    public function test_a_new_document_still_requires_number_date_and_year(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.legal-documents.store'), $this->payload(['document_number' => '', 'issue_date' => '', 'year' => '']))
            ->assertSessionHasErrors(['document_number', 'issue_date', 'year']);
    }

    public function test_an_imported_record_with_unknown_number_date_and_year_stays_editable(): void
    {
        $document = LegalDocument::create([
            'title' => 'ឯកសារនាំចូល', 'issuing_authority' => 'ក្រសួងសុខាភិបាល', 'status' => 'published',
            'external_url' => 'https://drive.google.com/uc?export=download&id=imp', 'external_format' => 'pdf',
        ]);

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.legal-documents.edit', $document))->assertOk();

        $this->actingAs($admin)->put(route('admin.legal-documents.update', $document), [
            'title' => 'ចំណងជើងកែតម្រូវ', 'issuing_authority' => 'ក្រសួងសុខាភិបាល',
            'external_url' => 'https://drive.google.com/uc?export=download&id=imp',
        ])->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame('ចំណងជើងកែតម្រូវ', $document->title);
        $this->assertNull($document->year);
        $this->assertNull($document->issue_date);

        // The admin can now supply the real values, including years before 1990.
        $this->actingAs($admin)->put(route('admin.legal-documents.update', $document), [
            'title' => 'ចំណងជើងកែតម្រូវ', 'issuing_authority' => 'ក្រសួងសុខាភិបាល', 'document_number' => '12',
            'issue_date' => '1987-03-05', 'year' => 1987,
        ])->assertSessionHasNoErrors();
        $this->assertSame(1987, $document->refresh()->year);
    }

    public function test_the_admin_list_shows_placeholders_for_unknown_values(): void
    {
        LegalDocument::create(['title' => 'ឯកសារគ្មានលេខ', 'issuing_authority' => 'ក្រសួងសុខាភិបាល', 'status' => 'published', 'external_url' => 'https://example.org/a.pdf']);

        $this->actingAs($this->admin())->get(route('admin.legal-documents.index'))
            ->assertOk()
            ->assertSee('ឯកសារគ្មានលេខ');
    }
}
