<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LegalDocumentRequest;
use App\Models\AuditLog;
use App\Models\LegalDocument;
use App\Models\LegalDocumentCategory;
use App\Services\MediaManagerService;
use App\Services\MediaUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegalDocumentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', LegalDocument::class);

        $query = LegalDocument::with('category');

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }
        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $documents = $query->orderByDesc('issue_date')->orderByDesc('year')->orderByDesc('id')->paginate(15)->withQueryString();
        $categories = LegalDocumentCategory::orderBy('name')->get();
        $years = LegalDocument::whereNotNull('year')->select('year')->distinct()->orderByDesc('year')->pluck('year');

        return view('admin.legal-documents.index', compact('documents', 'categories', 'years'));
    }

    public function create(): View
    {
        $this->authorize('create', LegalDocument::class);

        $categories = LegalDocumentCategory::orderBy('name')->get();

        return view('admin.legal-documents.create', compact('categories'));
    }

    public function store(LegalDocumentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['file']);

        $data['created_by'] = auth()->id();
        $data['status'] = 'draft';

        $document = LegalDocument::create($data);

        if ($request->hasFile('file')) {
            $folderId = app(MediaManagerService::class)->resolveFolderForRecord('legal-documents', $document, "#{$document->id} — {$document->title}");
            $document->update(['file_path' => MediaUploader::store($request->file('file'), 'legal-documents', folderId: $folderId)]);
        }

        AuditLog::record('created', 'LegalDocument', $document->id, "បង្កើតឯកសារ៖ {$document->title}");

        return redirect()->route('admin.legal-documents.edit', $document)->with('status', 'បានបង្កើតឯកសារដោយជោគជ័យ។');
    }

    public function edit(LegalDocument $legalDocument): View
    {
        $this->authorize('update', $legalDocument);

        $categories = LegalDocumentCategory::orderBy('name')->get();

        return view('admin.legal-documents.edit', ['document' => $legalDocument, 'categories' => $categories]);
    }

    public function update(LegalDocumentRequest $request, LegalDocument $legalDocument): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('file')) {
            MediaUploader::delete($legalDocument->file_path);
            $label = '#'.$legalDocument->id.' — '.($data['title'] ?? $legalDocument->title);
            $folderId = app(MediaManagerService::class)->resolveFolderForRecord('legal-documents', $legalDocument, $label);
            $data['file_path'] = MediaUploader::store($request->file('file'), 'legal-documents', folderId: $folderId);
        }

        $legalDocument->update($data);

        AuditLog::record('updated', 'LegalDocument', $legalDocument->id, "កែប្រែឯកសារ៖ {$legalDocument->title}");

        return redirect()->route('admin.legal-documents.edit', $legalDocument)->with('status', 'បានរក្សាទុកដោយជោគជ័យ។');
    }

    public function destroy(LegalDocument $legalDocument): RedirectResponse
    {
        $this->authorize('delete', $legalDocument);

        MediaUploader::delete($legalDocument->file_path);
        $legalDocument->delete();

        AuditLog::record('deleted', 'LegalDocument', $legalDocument->id, "លុបឯកសារ៖ {$legalDocument->title}");

        return redirect()->route('admin.legal-documents.index')->with('status', 'បានលុបឯកសារដោយជោគជ័យ។');
    }

    public function publish(LegalDocument $legalDocument): RedirectResponse
    {
        $this->authorize('publish', $legalDocument);

        $legalDocument->update(['status' => 'published', 'published_at' => now()]);

        AuditLog::record('published', 'LegalDocument', $legalDocument->id, "បានផ្សាយឯកសារ៖ {$legalDocument->title}");

        return back()->with('status', 'បានផ្សាយឯកសារដោយជោគជ័យ។');
    }

    public function archive(LegalDocument $legalDocument): RedirectResponse
    {
        $this->authorize('publish', $legalDocument);

        $legalDocument->update(['status' => 'archived']);

        AuditLog::record('unpublished', 'LegalDocument', $legalDocument->id, "បានទុកឯកសារក្នុងឯកសារ៖ {$legalDocument->title}");

        return back()->with('status', 'បានទុកឯកសារជាឯកសារយោង។');
    }
}
