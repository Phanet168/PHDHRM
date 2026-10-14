<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AboutItem;
use App\Models\AuditLog;
use App\Models\LegalDocument;
use App\Services\MediaManagerService;
use App\Support\AboutIcon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Core info cards, timeline milestones and related documents: one controller, keyed by {group}. */
class AboutItemController extends Controller
{
    public function index(Request $request, string $group): View
    {
        $this->authorize('about.manage');
        $trashed = $request->boolean('trashed');

        $items = AboutItem::inGroup($group)->with(['media', 'legalDocument'])->ordered()
            ->when($trashed, fn ($query) => $query->onlyTrashed())->get();

        return view('admin.about.items.index', [
            'group' => $group,
            'items' => $items,
            'trashed' => $trashed,
            'trashedCount' => AboutItem::inGroup($group)->onlyTrashed()->count(),
        ]);
    }

    public function create(string $group): View
    {
        $this->authorize('about.manage');

        return $this->form($group, new AboutItem(['group' => $group, 'is_active' => true, 'sort_order' => AboutItem::inGroup($group)->count()]));
    }

    public function store(Request $request, string $group): RedirectResponse
    {
        $this->authorize('about.manage');

        $item = AboutItem::create(['group' => $group, ...$this->validated($request, $group)]);
        AuditLog::record('created', 'AboutItem', $item->id, 'បន្ថែម '.AboutItem::GROUPS[$group].'៖ '.$item->title);

        return redirect()->route('admin.about.items.index', $group)->with('status', 'បានបន្ថែមដោយជោគជ័យ។');
    }

    public function edit(string $group, AboutItem $item): View
    {
        $this->authorize('about.manage');
        abort_unless($item->group === $group, 404);

        return $this->form($group, $item);
    }

    public function update(Request $request, string $group, AboutItem $item): RedirectResponse
    {
        $this->authorize('about.manage');
        abort_unless($item->group === $group, 404);

        $item->update($this->validated($request, $group));
        AuditLog::record('updated', 'AboutItem', $item->id, 'កែសម្រួល '.AboutItem::GROUPS[$group].'៖ '.$item->title);

        return redirect()->route('admin.about.items.index', $group)->with('status', 'បានរក្សាទុកដោយជោគជ័យ។');
    }

    public function toggle(string $group, AboutItem $item): RedirectResponse
    {
        $this->authorize('about.manage');
        abort_unless($item->group === $group, 404);

        $item->update(['is_active' => ! $item->is_active]);
        AuditLog::record($item->is_active ? 'published' : 'unpublished', 'AboutItem', $item->id, ($item->is_active ? 'បើកប្រើ' : 'បិទប្រើ').'៖ '.$item->title);

        return back()->with('status', 'បានផ្លាស់ប្តូរស្ថានភាពដោយជោគជ័យ។');
    }

    public function destroy(string $group, AboutItem $item): RedirectResponse
    {
        $this->authorize('about.manage');
        abort_unless($item->group === $group, 404);

        // Soft delete only; the referenced Media file is never touched.
        $item->delete();
        AuditLog::record('deleted', 'AboutItem', $item->id, 'លុប '.AboutItem::GROUPS[$group].'៖ '.$item->title);

        return back()->with('status', 'បានលុបដោយជោគជ័យ។ អាចស្ដារវិញពីធុងសំរាម។');
    }

    public function restore(string $group, int $item): RedirectResponse
    {
        $this->authorize('about.manage');
        $item = AboutItem::onlyTrashed()->inGroup($group)->findOrFail($item);

        $item->restore();
        AuditLog::record('restored', 'AboutItem', $item->id, 'ស្ដារ៖ '.$item->title);

        return back()->with('status', 'បានស្ដារវិញដោយជោគជ័យ។');
    }

    public function forceDestroy(string $group, int $item): RedirectResponse
    {
        $this->authorize('about.manage');
        $item = AboutItem::onlyTrashed()->inGroup($group)->findOrFail($item);

        $item->forceDelete();
        AuditLog::record('force_deleted', 'AboutItem', $item->id, 'លុបជាអចិន្ត្រៃយ៍៖ '.$item->title);

        return back()->with('status', 'បានលុបជាអចិន្ត្រៃយ៍។ ឯកសារក្នុង Media Library មិនត្រូវបានលុបទេ។');
    }

    public function reorder(Request $request, string $group): JsonResponse
    {
        $this->authorize('about.manage');
        $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($request->input('items') as $row) {
            AboutItem::inGroup($group)->whereKey($row['id'])->update(['sort_order' => $row['sort_order']]);
        }
        AuditLog::record('reordered', 'AboutItem', null, 'តម្រៀបលំដាប់ '.AboutItem::GROUPS[$group]);

        return response()->json(['status' => 'ok']);
    }

    private function form(string $group, AboutItem $item): View
    {
        return view('admin.about.items.form', [
            'group' => $group,
            'item' => $item,
            'folderId' => app(MediaManagerService::class)->resolveFolderForCollection('about'),
            'legalDocuments' => $group === 'documents' ? LegalDocument::published()->orderByDesc('id')->pluck('title', 'id') : collect(),
        ]);
    }

    private function validated(Request $request, string $group): array
    {
        $common = [
            'title' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
        $rules = match ($group) {
            'cards' => [
                'body' => ['nullable', 'string', 'max:500'],
                'icon_key' => ['required', Rule::in(array_keys(AboutIcon::ICONS))],
                'accent' => ['required', Rule::in(array_keys(AboutIcon::ACCENTS))],
                // Page anchor, site path or http(s) URL only — never javascript: and friends.
                'link_url' => ['nullable', 'string', 'max:2048', 'regex:/^(#[A-Za-z0-9_-]+|\/(?!\/)\S*|https?:\/\/\S+)$/'],
            ],
            'timeline' => [
                'date_label' => ['required', 'string', 'max:50'],
                'body' => ['nullable', 'string', 'max:1000'],
                'icon_key' => ['nullable', Rule::in(array_keys(AboutIcon::ICONS))],
            ],
            'documents' => [
                'source' => ['required', Rule::in(['media', 'legal'])],
                'media_id' => ['nullable', 'required_if:source,media', 'integer', Rule::exists('media', 'id')->whereNull('deleted_at')],
                'legal_document_id' => ['nullable', 'required_if:source,legal', 'integer', Rule::exists('legal_documents', 'id')->where('status', 'published')->whereNull('deleted_at')],
            ],
        };
        $data = $request->validate([...$common, ...$rules], [
            'link_url.regex' => 'តំណភ្ជាប់ត្រូវចាប់ផ្ដើមដោយ #, / ឬ https://',
            'media_id.required_if' => 'សូមជ្រើសរើសឯកសារពី Media Library។',
            'legal_document_id.required_if' => 'សូមជ្រើសរើសលិខិតបទដ្ឋាន។',
        ]);

        if ($group === 'documents') {
            $useMedia = $data['source'] === 'media';
            $data['media_id'] = $useMedia ? $data['media_id'] : null;
            $data['legal_document_id'] = $useMedia ? null : $data['legal_document_id'];
            unset($data['source']);
        }
        if (array_key_exists('body', $data)) {
            $data['body'] = $data['body'] !== null ? strip_tags($data['body']) : null;
        }

        $data['sort_order'] ??= AboutItem::inGroup($group)->count();
        $data['is_active'] = $request->boolean('is_active');
        $data['is_demo'] = false;

        return $data;
    }
}
