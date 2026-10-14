<?php

namespace Tests\Feature;

use App\Models\LegalDocument;
use App\Models\MediaFolder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegalDocumentMediaFolderTest extends TestCase
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
            'title' => 'លិខិតបទដ្ឋានគតិយុត្តសាកល្បង',
            'document_number' => '001/26',
            'issuing_authority' => 'មន្ទីរសុខាភិបាលខេត្តស្ទឹងត្រែង',
            'issue_date' => '2026-01-01',
            'year' => 2026,
        ], $overrides);
    }

    public function test_creating_a_document_files_it_into_its_own_dedicated_folder(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin())->post(route('admin.legal-documents.store'), $this->payload([
            'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ]));

        $response->assertRedirect();
        $document = LegalDocument::where('title', 'លិខិតបទដ្ឋានគតិយុត្តសាកល្បង')->firstOrFail();
        $this->assertNotEmpty($document->file_path);

        $folder = MediaFolder::where('owner_type', LegalDocument::class)->where('owner_id', $document->id)->first();
        $this->assertNotNull($folder);
        $this->assertSame("#{$document->id} — លិខិតបទដ្ឋានគតិយុត្តសាកល្បង", $folder->name);
        $this->assertSame('លិខិតបទដ្ឋានគតិយុត្ត', $folder->parent->name);
    }

    public function test_replacing_the_file_on_update_reuses_the_same_document_folder(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.legal-documents.store'), $this->payload([
            'file' => UploadedFile::fake()->create('first.pdf', 100, 'application/pdf'),
        ]));
        $document = LegalDocument::firstOrFail();

        $this->actingAs($admin)->put(route('admin.legal-documents.update', $document), $this->payload([
            'file' => UploadedFile::fake()->create('second.pdf', 100, 'application/pdf'),
        ]));

        $this->assertSame(
            1,
            MediaFolder::where('owner_type', LegalDocument::class)->where('owner_id', $document->id)->count()
        );
    }
}
