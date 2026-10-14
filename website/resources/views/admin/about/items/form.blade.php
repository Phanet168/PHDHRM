@php
    $labels = ['cards' => 'កាតព័ត៌មានស្នូល', 'timeline' => 'ព្រឹត្តិការណ៍ប្រវត្តិ', 'documents' => 'ឯកសារពាក់ព័ន្ធ'];
    $source = old('source', $item->legal_document_id ? 'legal' : 'media');
@endphp
<x-admin.layout :title="($item->exists ? 'កែសម្រួល' : 'បន្ថែម').' · '.$labels[$group]">
    @include('admin.about._nav')

    <form method="POST" action="{{ $item->exists ? route('admin.about.items.update', [$group, $item]) : route('admin.about.items.store', $group) }}"
          class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4 max-w-[860px]">
        @csrf
        @if ($item->exists) @method('PUT') @endif

        <div class="flex items-center justify-between gap-3">
            <p class="font-bold text-brand-navy text-[16px]">{{ $item->exists ? 'កែសម្រួល' : 'បន្ថែម' }} {{ $labels[$group] }}</p>
            @if ($item->is_demo)<span class="bg-amber-100 text-amber-700 rounded-full px-3 py-1 text-[12px] font-bold">DEMO — សូមជំនួសដោយខ្លឹមសារផ្លូវការ</span>@endif
        </div>

        @if ($group === 'timeline')
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-admin.form-input name="date_label" label="ឆ្នាំ ឬកាលបរិច្ឆេទ" :value="$item->date_label" placeholder="ឧ. ២០២៤ ឬ ខែមករា ២០២៤" maxlength="50" required />
                <div class="md:col-span-2"><x-admin.form-input name="title" label="ចំណងជើង" :value="$item->title" maxlength="255" required /></div>
            </div>
            <x-admin.textarea name="body" label="ការពិពណ៌នា" :value="$item->body" rows="3" maxlength="1000" />
        @else
            <x-admin.form-input name="title" label="ចំណងជើង" :value="$item->title" maxlength="255" required />
        @endif

        @if ($group === 'cards')
            <x-admin.textarea name="body" label="ការពិពណ៌នាខ្លី" :value="$item->body" rows="3" maxlength="500" />
            <x-admin.form-input name="link_url" label="តំណភ្ជាប់ «អានបន្ថែម» (ស្រេចចិត្ត — #anchor, /path ឬ https://)" :value="$item->link_url" placeholder="#history" />
        @endif

        @if ($group !== 'documents')
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-admin.select name="icon_key" label="រូបតំណាង (Icon)" :options="\App\Support\AboutIcon::ICONS" :value="$item->icon_key" :placeholder="$group === 'cards' ? null : '— លំនាំដើម —'" :required="$group === 'cards'" />
                @if ($group === 'cards')
                    <x-admin.select name="accent" label="ពណ៌" :options="\App\Support\AboutIcon::ACCENTS" :value="$item->accent ?? 'blue'" :placeholder="null" required />
                @endif
                <x-admin.form-input name="sort_order" label="លំដាប់" type="number" min="0" :value="$item->sort_order" />
            </div>
        @else
            <fieldset class="flex flex-col gap-3" x-data="{ source: @js($source) }">
                <legend class="text-ink text-[13px] font-bold mb-2">ប្រភពឯកសារ</legend>
                <div class="flex flex-wrap gap-6">
                    <label class="flex items-center gap-2 text-[13px]"><input type="radio" name="source" value="media" x-model="source"> ឯកសារពី Media Library</label>
                    <label class="flex items-center gap-2 text-[13px]"><input type="radio" name="source" value="legal" x-model="source"> លិខិតបទដ្ឋានគតិយុត្ត (បានផ្សាយ)</label>
                </div>
                <div x-show="source === 'media'">
                    @if ($item->media)<p class="text-muted text-[12px] mb-2">បច្ចុប្បន្ន៖ {{ $item->media->original_filename }} · {{ $item->media->formattedSize() }}</p>@endif
                    @include('admin.pages._media-picker', ['name' => 'media_id', 'selected' => $item->media_id, 'defaultFolderId' => $folderId, 'mimeType' => 'application/%'])
                    <p class="text-muted text-[11px] mt-1">Upload ឯកសារថ្មីនៅ Media Library ជាមុនសិន (ប៊ូតុង «បើក Media Library ពេញ») ដើម្បីកុំឲ្យមានឯកសារស្ទួន។</p>
                </div>
                <div x-show="source === 'legal'" x-cloak>
                    <x-admin.select name="legal_document_id" label="លិខិតបទដ្ឋាន" :options="$legalDocuments" :value="$item->legal_document_id" />
                </div>
            </fieldset>
            <x-admin.form-input name="sort_order" label="លំដាប់" type="number" min="0" :value="$item->sort_order" class="max-w-[200px]" />
        @endif

        <label class="flex items-center gap-2 text-[13px] font-bold text-ink">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active ?? true)) class="rounded border-line">
            បើកប្រើ / បោះផ្សាយ
        </label>

        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('admin.about.items.index', $group) }}" class="text-muted text-[14px] font-bold px-4 py-2">បោះបង់</a>
            <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6">រក្សាទុក</button>
        </div>
    </form>
</x-admin.layout>
