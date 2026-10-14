@php $news = $news; @endphp

{{-- Attachments (PDF/DOC/XLS…): kept separate from the image gallery so admins never confuse the
     two. "ដកចេញ" only detaches — the file stays in the Media Library, permanent deletion only
     ever happens there. --}}
<div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-5 flex flex-col gap-3">
    <div class="flex items-center gap-2">
        <span class="flex items-center justify-center size-7 rounded-[6px] bg-brand-blue-tint text-brand-blue text-[14px]">📎</span>
        <p class="text-brand-navy text-[15px] font-bold">ឯកសារភ្ជាប់ ({{ $news->attachments->count() }})</p>
    </div>
    <p class="text-muted text-[11px] -mt-2">PDF, DOC, DOCX, XLS, XLSX — អូសដៃចាប់ ⠿ ដើម្បីតម្រៀបលំដាប់។</p>

    <div data-attachment-sort-root data-reorder-url="{{ route('admin.news.attachments.reorder', $news) }}">
        <div class="news-attachment-list flex flex-col gap-2">
            @forelse ($news->attachments as $attachment)
                <div class="attachment-item flex items-center gap-3 border border-line rounded-[8px] p-2" data-id="{{ $attachment->id }}" x-data="{ editing: false }">
                    <span class="attachment-drag-handle shrink-0 cursor-move text-muted text-[14px] px-1" aria-label="អូសដើម្បីតម្រៀប">⠿</span>
                    <span class="shrink-0"><x-media-file-icon :type="$attachment->iconType()" :size="34" /></span>

                    <div class="min-w-0 flex-1">
                        <template x-if="!editing">
                            <p class="text-ink text-[13px] font-bold truncate">{{ $attachment->displayName() }}</p>
                        </template>
                        <form x-show="editing" x-cloak method="POST" action="{{ route('admin.news.attachments.update', [$news, $attachment]) }}" class="flex gap-1" @submit="editing = false">
                            @csrf @method('PUT')
                            <input type="text" name="label" value="{{ $attachment->label }}" placeholder="{{ $attachment->media?->original_filename }}" class="flex-1 min-w-0 text-[12px] border border-line rounded px-2 py-1">
                            <button type="submit" class="text-brand-blue text-[11px] font-bold px-2">រក្សាទុក</button>
                        </form>
                        <p class="text-muted text-[11px]">{{ strtoupper($attachment->iconType()) }} @if ($attachment->formattedSize()) · {{ $attachment->formattedSize() }} @endif</p>
                    </div>

                    <div class="shrink-0 flex items-center gap-1">
                        <a href="{{ $attachment->url() }}" target="_blank" rel="noopener" class="news-row-action" title="មើល" aria-label="មើលឯកសារ"><x-news-icon name="eye" width="15" height="15" /></a>
                        <a href="{{ $attachment->url() }}" download class="news-row-action" title="ទាញយក" aria-label="ទាញយកឯកសារ"><x-news-icon name="download" width="15" height="15" /></a>
                        <button type="button" @click="editing = !editing" class="news-row-action" title="ប្តូរឈ្មោះ" aria-label="ប្តូរឈ្មោះឯកសារ"><x-news-icon name="pencil" width="15" height="15" /></button>
                        <form method="POST" action="{{ route('admin.news.attachments.destroy', [$news, $attachment]) }}" onsubmit="return confirm('តើអ្នកចង់ដកឯកសារនេះចេញពីអត្ថបទមែនទេ?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="news-row-action is-danger" title="ដកចេញ" aria-label="ដកឯកសារចេញ"><x-news-icon name="trash" width="15" height="15" /></button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-muted text-[13px]">មិនទាន់មានឯកសារភ្ជាប់ទេ។</p>
            @endforelse
        </div>
    </div>

    <details class="border-t border-line pt-3">
        <summary class="text-brand-blue text-[13px] font-bold cursor-pointer">+ បន្ថែមឯកសារពី Media Library</summary>
        <form method="POST" action="{{ route('admin.news.attachments.from-media', $news) }}" class="mt-3">
            @csrf
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2 max-h-[220px] overflow-y-auto mb-3">
                @foreach (\App\Models\Media::whereIn('mime_type', \App\Services\MediaUploader::LIBRARY_MIMES)->latest()->take(30)->get() as $media)
                    <label class="relative flex items-center gap-2 border border-line rounded-[6px] p-2">
                        <input type="checkbox" name="media_ids[]" value="{{ $media->id }}" class="shrink-0 rounded">
                        <x-media-file-icon :type="$media->iconType()" :size="24" />
                        <span class="text-[11px] text-ink truncate">{{ $media->original_filename }}</span>
                    </label>
                @endforeach
            </div>
            <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-9 px-4">បន្ថែមដែលបានជ្រើសរើស</button>
        </form>
    </details>
</div>
