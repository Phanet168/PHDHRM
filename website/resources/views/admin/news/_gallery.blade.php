@php $news = $news; @endphp

{{-- Gallery Images: every item is a Media Library reference. "ដកចេញ" only detaches it from this
     article's gallery — the file and its Media Library record are never deleted here. Permanent
     file deletion only ever happens inside the Media Library itself. --}}
<div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-5 flex flex-col gap-3"
     x-data="{ lightbox: null }" @keydown.escape.window="lightbox = null">
    <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-2">
            <span class="flex items-center justify-center size-7 rounded-[6px] bg-brand-blue-tint text-brand-blue text-[14px]">🖼</span>
            <p class="text-brand-navy text-[15px] font-bold">រូបភាពបន្ថែម / Gallery Images ({{ $news->images->count() }})</p>
        </div>
    </div>
    <p class="text-muted text-[11px] -mt-2">អូសដៃចាប់ ⠿ ដើម្បីតម្រៀបលំដាប់។ "ដកចេញ" ដកតែពីវិចិត្រសាលនេះប៉ុណ្ណោះ រូបភាពនៅតែមានក្នុង Media Library។</p>

    <div data-gallery-sort-root data-reorder-url="{{ route('admin.news.images.reorder', $news) }}">
        <div class="content-gallery-list flex flex-col gap-2">
            @forelse ($news->images as $image)
                <div class="gallery-item flex items-center gap-3 border border-line rounded-[8px] p-2" data-id="{{ $image->id }}">
                    <span class="gallery-drag-handle shrink-0 cursor-move text-muted text-[14px] px-1" aria-label="អូសដើម្បីតម្រៀប">⠿</span>
                    <span class="shrink-0 flex items-center justify-center size-6 rounded-full bg-brand-navy text-white text-[11px] font-bold">{{ $loop->iteration }}</span>
                    <button type="button" class="shrink-0" @click="lightbox = {
                            url: @js($image->url()),
                            name: @js($image->media?->original_filename ?? basename($image->image ?? '')),
                            dims: @js($image->media?->dimensions()),
                            size: @js($image->media?->formattedSize()),
                            caption: @js($image->captionText()),
                        }" aria-label="មើលរូបភាពពេញ">
                        <img src="{{ $image->url() }}" alt="{{ $image->altText() }}" class="size-14 object-cover rounded-[6px] border border-line">
                    </button>

                    <div class="min-w-0 flex-1" x-data="{ editing: false }">
                        <p class="text-ink text-[13px] font-bold truncate">{{ $image->media?->original_filename ?? basename($image->image ?? '') }}</p>
                        <p class="text-muted text-[11px]">
                            @if ($image->media?->dimensions()) {{ $image->media->dimensions() }} @endif
                            @if ($image->media?->formattedSize()) · {{ $image->media->formattedSize() }} @endif
                        </p>

                        <template x-if="!editing">
                            <p class="text-muted text-[11px] truncate">{{ $image->captionText() ?: '—' }}</p>
                        </template>
                        <form x-show="editing" x-cloak method="POST" action="{{ route('admin.news.images.update', [$news, $image]) }}" class="flex gap-1 mt-1" @submit="editing = false">
                            @csrf @method('PUT')
                            <input type="text" name="caption" value="{{ $image->caption }}" placeholder="Caption" class="flex-1 min-w-0 text-[12px] border border-line rounded px-2 py-1">
                            <button type="submit" class="text-brand-blue text-[11px] font-bold px-2">រក្សាទុក</button>
                        </form>

                        <div class="flex items-center gap-1 mt-1">
                            <button type="button" @click="lightbox = {
                                    url: @js($image->url()),
                                    name: @js($image->media?->original_filename ?? basename($image->image ?? '')),
                                    dims: @js($image->media?->dimensions()),
                                    size: @js($image->media?->formattedSize()),
                                    caption: @js($image->captionText()),
                                }" class="news-row-action" title="មើល" aria-label="មើលរូបភាពពេញ"><x-news-icon name="eye" width="15" height="15" /></button>
                            <button type="button" @click="editing = !editing" class="news-row-action" title="កែ Caption" aria-label="កែ Caption"><x-news-icon name="pencil" width="15" height="15" /></button>
                            <form method="POST" action="{{ route('admin.news.images.destroy', [$news, $image]) }}" onsubmit="return confirm('តើអ្នកចង់ដករូបនេះចេញពីអត្ថបទមែនទេ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="news-row-action is-danger" title="ដកចេញ" aria-label="ដករូបចេញពីវិចិត្រសាល"><x-news-icon name="trash" width="15" height="15" /></button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-muted text-[13px]">មិនទាន់មានរូបភាពទេ។</p>
            @endforelse
        </div>
    </div>

    <details class="border-t border-line pt-3">
        <summary class="text-brand-blue text-[13px] font-bold cursor-pointer">+ បន្ថែមរូបពី Media Library</summary>
        <form method="POST" action="{{ route('admin.news.images.from-media', $news) }}" class="mt-3">
            @csrf
            <div class="grid grid-cols-3 md:grid-cols-4 gap-2 max-h-[220px] overflow-y-auto mb-3">
                @foreach (\App\Models\Media::where('mime_type', 'like', 'image/%')->latest()->take(48)->get() as $media)
                    <label class="relative">
                        <input type="checkbox" name="media_ids[]" value="{{ $media->id }}" class="absolute top-1 left-1 rounded">
                        <img src="{{ $media->url }}" title="{{ $media->original_filename }}" class="h-[70px] w-full object-cover rounded-[6px] border border-line">
                    </label>
                @endforeach
            </div>
            <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-9 px-4">បន្ថែមដែលបានជ្រើសរើស</button>
        </form>
    </details>

    {{-- Lightbox: one shared modal, opened by any "មើល" action above. --}}
    <div x-show="lightbox" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-5 bg-black/70" @click.self="lightbox = null" role="dialog" aria-modal="true">
        <div class="bg-white rounded-[10px] max-w-[640px] w-full max-h-[90vh] overflow-y-auto" @click.outside="lightbox = null">
            <div class="flex items-center justify-between p-3 border-b border-line">
                <p class="text-ink text-[13px] font-bold truncate" x-text="lightbox?.name"></p>
                <button type="button" @click="lightbox = null" class="text-muted text-[18px] leading-none px-2" aria-label="បិទ">✕</button>
            </div>
            <img :src="lightbox?.url" class="w-full max-h-[60vh] object-contain bg-surface" alt="">
            <div class="p-3 flex flex-col gap-1 text-[12px] text-muted">
                <p x-show="lightbox?.dims || lightbox?.size" x-text="[lightbox?.dims, lightbox?.size].filter(Boolean).join(' · ')"></p>
                <p x-show="lightbox?.caption" x-text="lightbox?.caption"></p>
            </div>
        </div>
    </div>
</div>
