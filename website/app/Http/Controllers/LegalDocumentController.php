<?php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use App\Models\LegalDocumentCategory;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Partner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LegalDocumentController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'year' => ['nullable', 'integer', 'between:1,9999'],
            'type' => ['nullable', 'integer', 'min:1'],
            'issuing_body' => ['nullable', 'string', 'max:255'],
            'keyword' => ['nullable', 'string', 'max:255'],
        ]);

        $query = LegalDocument::published()->with('category');

        if ($request->filled('year')) {
            $query->where('year', $request->input('year'));
        }

        if ($request->filled('type')) {
            $query->where('category_id', $request->input('type'));
        }

        if ($request->filled('issuing_body')) {
            $query->where('issuing_authority', $request->input('issuing_body'));
        }

        if ($request->filled('keyword')) {
            $query->where('title', 'like', '%'.$request->input('keyword').'%');
        }

        // year + id break ties so records without an exact date paginate deterministically.
        $documents = $query->orderByDesc('issue_date')->orderByDesc('year')->orderByDesc('id')->paginate(10)->withQueryString();

        $years = LegalDocument::published()->whereNotNull('year')->select('year')->distinct()->orderByDesc('year')->pluck('year');
        $issuingBodies = LegalDocument::published()->select('issuing_authority')->distinct()->orderBy('issuing_authority')->pluck('issuing_authority');
        $categories = LegalDocumentCategory::withCount(['documents' => fn ($query) => $query->published()])
            ->orderBy('sort_order')->orderBy('id')->get();
        $publishedCount = LegalDocument::published()->count();
        $usefulLinks = $this->usefulLinks();

        return view('legal-documents.index', compact('documents', 'years', 'issuingBodies', 'categories', 'publishedCount', 'usefulLinks'));
    }

    /**
     * Sidebar "useful links", built only from links an admin already
     * configured: active partners that have a website, then the footer menu.
     * Nothing is hardcoded; the card is hidden when both sources are empty.
     */
    private function usefulLinks(): Collection
    {
        $partners = Partner::active()->where('website', 'like', 'http%')
            ->orderBy('sort_order')->get(['id', 'name', 'website'])
            ->map(fn (Partner $partner) => ['label' => $partner->name, 'url' => $partner->website, 'external' => true]);

        $footer = Menu::tree('footer')
            ->map(fn (MenuItem $item) => ['label' => $item->label_km, 'url' => $item->resolveUrl(), 'external' => $item->target === '_blank'])
            ->reject(fn (array $link) => $link['url'] === '#');

        return $partners->concat($footer)->take(8)->values();
    }

    public function download(LegalDocument $legalDocument): StreamedResponse|RedirectResponse
    {
        abort_unless($legalDocument->status === 'published', 404);

        // Documents that link to a file hosted elsewhere keep working through the same route.
        if (! $legalDocument->file_path && $legalDocument->external_url) {
            return redirect()->away($legalDocument->external_url);
        }

        abort_unless($legalDocument->file_path && Storage::disk('public')->exists($legalDocument->file_path), 404);

        return Storage::disk('public')->download($legalDocument->file_path, $legalDocument->title.'.pdf');
    }
}
