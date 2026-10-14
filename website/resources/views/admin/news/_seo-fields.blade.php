@php $news = $news ?? null; @endphp

<div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-5 flex flex-col gap-4">
    <div class="flex items-center gap-2">
        <span class="flex items-center justify-center size-7 rounded-[6px] bg-brand-blue-tint text-brand-blue text-[14px]">🔍</span>
        <p class="text-brand-navy text-[15px] font-bold">SEO និងការបោះផ្សាយ</p>
    </div>

    <x-admin.form-input name="published_at" type="date" label="កាលបរិច្ឆេទផ្សាយ" :value="$news?->published_at?->format('Y-m-d')" />
    <x-admin.form-input name="meta_title" label="ចំណងជើង SEO (Meta Title)" :value="$news?->meta_title" placeholder="ទុកទទេ ដើម្បីប្រើចំណងជើងអត្ថបទ" />
    <x-admin.textarea name="meta_description" label="ការពិពណ៌នា SEO (Meta Description)" :value="$news?->meta_description" rows="3" placeholder="ទុកទទេ ដើម្បីប្រើសេចក្តីសង្ខេប" />
</div>
