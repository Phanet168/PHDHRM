@php $news = $news ?? null; @endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <x-admin.form-input name="title_km" label="ចំណងជើង (ខ្មែរ)" :value="$news?->title_km" required />
    <x-admin.form-input name="title_en" label="ចំណងជើង (អង់គ្លេស)" :value="$news?->title_en" />
    <x-admin.form-input name="slug" label="Slug" :value="$news?->slug" required />
    <x-admin.select name="category_id" label="ប្រភេទ" :value="$news?->category_id" :options="$categories->pluck('name_km', 'id')" />
    <x-admin.select name="facility_id" label="មូលដ្ឋានពាក់ព័ន្ធ" :value="$news?->facility_id" :options="$facilities->pluck('name_km', 'id')" placeholder="ព័ត៌មានទូទៅ (មិនកំណត់មូលដ្ឋាន)" />
    <x-admin.form-input name="published_at" type="date" label="កាលបរិច្ឆេទផ្សាយ" :value="$news?->published_at?->format('Y-m-d')" />
</div>

<x-admin.textarea name="excerpt" label="សេចក្តីសង្ខេប" :value="$news?->excerpt" rows="2" />
<x-admin.textarea name="content" label="ខ្លឹមសារ" :value="$news?->content" rows="8" />

{{-- Featured image: the Media Library is the single source of truth (featured_media_id).
     Picking a different item, uploading a new file, or removing it all update the same field;
     the preview updates live for a Library pick (Alpine), same pattern as Homepage Quick Links. --}}
<div class="flex flex-col gap-2"
     x-data="{ previewUrl: @js($news?->featuredImageUrl() ?? asset(config('site.default_news_image'))) }"
     @change="if ($event.target.name === 'featured_media_id') { previewUrl = $event.target.value ? $event.target.closest('label')?.querySelector('img')?.getAttribute('src') : @js(asset(config('site.default_news_image'))) }">
    <p class="text-ink text-[13px] font-bold">រូបភាពគម្រប (Featured Image)</p>
    <img :src="previewUrl" class="w-full max-w-[380px] aspect-video object-cover rounded-[8px] border border-line">

    @include('admin.pages._media-picker', [
        'name' => 'featured_media_id',
        'selected' => $news?->featured_media_id,
        'defaultFolderId' => $newsFolderId ?? null,
    ])
    @error('featured_media_id')<p class="text-brand-red text-[12px]">{{ $message }}</p>@enderror

    <div class="flex flex-wrap items-end gap-3">
        <x-admin.upload-field name="featured_image" label="ឬ Upload ឯកសារថ្មី" />
        <button type="button"
            @click="
                const none = $el.closest('form').querySelector('.media-picker input[value=\'\']');
                none.checked = true;
                none.dispatchEvent(new Event('change', { bubbles: true }));
                $el.closest('form').querySelector('[name=remove_featured_image]').value = '1';
            "
            class="text-brand-red font-bold text-[13px] rounded-[8px] h-9 px-4 border border-line">
            លុបរូបគម្រប
        </button>
        <input type="hidden" name="remove_featured_image" value="0">
    </div>
    @error('featured_image')<p class="text-brand-red text-[12px]">{{ $message }}</p>@enderror
</div>

<div class="flex flex-col gap-2">
    <label for="news-gallery-images" class="block text-ink text-[13px] font-bold">រូបថតសកម្មភាព (អាចជ្រើសរើសច្រើន)</label>
    <input id="news-gallery-images" type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp"
           class="block w-full text-[13px] text-muted file:mr-3 file:py-2 file:px-4 file:rounded-[8px] file:border-0 file:bg-brand-blue-tint file:text-brand-blue file:font-bold file:text-[13px]">
    <p class="text-muted text-[12px]">អាចបន្ថែមរូបភាព JPG, PNG ឬ WEBP ប៉ុន្មានសន្លឹកក៏បាន មិនកំណត់ចំនួនទេ (រូបនីមួយៗអតិបរមា៤MB)។ ប្រសិនបើមានច្រើនសន្លឹកខ្លាំង សូមជ្រើសរើសជាបណ្តើរ ហើយចុច "រក្សាទុក" ម្តងៗ ព្រោះកម្មវិធីរុករកមួយចំនួនកំណត់ចំនួនឯកសារ Upload ក្នុងលើកតែមួយ។</p>
    @error('images')
        <p class="text-brand-red text-[12px]">{{ $message }}</p>
    @enderror
    @error('images.*')
        <p class="text-brand-red text-[12px]">{{ $message }}</p>
    @enderror
</div>

{{-- The saved gallery itself (preview grid, drag-reorder, remove, add existing Media) only
     exists once the article has an id — that block lives in edit.blade.php. --}}
@if ($news?->exists && $news->images->isNotEmpty())
    <p class="text-muted text-[12px]">រូបថតសកម្មភាពដែលបានភ្ជាប់ស្រាប់ ({{ $news->images->count() }}) អាចគ្រប់គ្រងខាងក្រោមទំព័រនេះ បន្ទាប់ពីរក្សាទុក។</p>
@endif

<label class="flex items-center gap-2 text-[13px] text-ink">
    <input type="checkbox" name="is_featured" value="1" @checked($news?->is_featured) class="rounded border-line">
    ព័ត៌មានលេចធ្លោ
</label>
