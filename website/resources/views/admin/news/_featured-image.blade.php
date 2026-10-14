@php $news = $news ?? null; @endphp

{{-- Featured Image: completely separate from the Gallery below. The Media Library is the single
     source of truth (featured_media_id); the preview updates live the moment a Library item is
     picked (Alpine), same pattern as Homepage Quick Links / Page Banners. --}}
<div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-5 flex flex-col gap-3"
     x-data="{ previewUrl: @js($news?->featuredImageUrl() ?? asset(config('site.default_news_image'))), hasImage: @js((bool) $news?->featured_media_id) }"
     @change="if ($event.target.name === 'featured_media_id') {
         hasImage = !!$event.target.value;
         previewUrl = $event.target.value ? $event.target.closest('label')?.querySelector('img')?.getAttribute('src') : @js(asset(config('site.default_news_image')));
     }">
    <div class="flex items-center gap-2">
        <span class="flex items-center justify-center size-7 rounded-[6px] bg-brand-blue-tint text-brand-blue text-[14px]">🖼</span>
        <p class="text-brand-navy text-[15px] font-bold">រូបតំណាង / Featured Image</p>
    </div>

    <img :src="previewUrl" class="w-full aspect-video object-cover rounded-[10px] border border-line bg-surface">

    <template x-if="hasImage">
        <p class="text-muted text-[12px] -mt-1">
            {{ $news?->featuredMedia?->original_filename }}
            @if ($news?->featuredMedia?->dimensions()) · {{ $news->featuredMedia->dimensions() }} @endif
            @if ($news?->featuredMedia?->formattedSize()) · {{ $news->featuredMedia->formattedSize() }} @endif
        </p>
    </template>
    <template x-if="!hasImage">
        <p class="text-muted text-[12px] -mt-1">មិនទាន់មានរូបតំណាងទេ — កំពុងប្រើរូបលំនាំដើម។</p>
    </template>

    @include('admin.pages._media-picker', [
        'name' => 'featured_media_id',
        'selected' => $news?->featured_media_id,
        'defaultFolderId' => $newsFolderId ?? null,
    ])
    @error('featured_media_id')<p class="text-brand-red text-[12px]">{{ $message }}</p>@enderror

    <div class="flex flex-wrap items-center gap-2 pt-1">
        <x-admin.upload-field name="featured_image" label="ឬ Upload រូបភាពថ្មី (ជំនួស/ប្តូររូប)" />
    </div>
    @error('featured_image')<p class="text-brand-red text-[12px]">{{ $message }}</p>@enderror

    <button type="button"
        x-show="hasImage" x-cloak
        @click="
            const none = $el.closest('form').querySelector('.media-picker input[value=\'\']');
            none.checked = true;
            none.dispatchEvent(new Event('change', { bubbles: true }));
            $el.closest('form').querySelector('[name=remove_featured_image]').value = '1';
        "
        class="self-start text-brand-red font-bold text-[13px] rounded-[8px] h-9 px-4 border border-line hover:bg-red-50">
        ដករូបចេញ
    </button>
    <input type="hidden" name="remove_featured_image" value="0">
    <p class="text-muted text-[11px]">"ដករូបចេញ" ដកតែពីអត្ថបទនេះប៉ុណ្ណោះ — មិនលុបឯកសារពី Media Library ទេ។</p>
</div>
