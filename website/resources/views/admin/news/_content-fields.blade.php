@php $news = $news ?? null; @endphp

<div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-5 flex flex-col gap-4">
    <div class="flex items-center gap-2">
        <span class="flex items-center justify-center size-7 rounded-[6px] bg-brand-blue-tint text-brand-blue text-[14px]">✎</span>
        <p class="text-brand-navy text-[15px] font-bold">មាតិកា</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <x-admin.form-input name="title_km" label="ចំណងជើង (ខ្មែរ)" :value="$news?->title_km" required />
        <x-admin.form-input name="title_en" label="ចំណងជើង (អង់គ្លេស)" :value="$news?->title_en" />
        <x-admin.form-input name="slug" label="Slug" :value="$news?->slug" required />
        <x-admin.select name="category_id" label="ប្រភេទ" :value="$news?->category_id" :options="$categories->pluck('name_km', 'id')" />
    </div>

    <x-admin.textarea name="excerpt" label="សេចក្តីសង្ខេប" :value="$news?->excerpt" rows="2" />
    <x-admin.rich-editor name="content" label="ខ្លឹមសារ" :value="$news?->content" rows="12" />
</div>
