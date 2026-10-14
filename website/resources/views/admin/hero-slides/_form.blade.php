@php $slide = $slide ?? null; @endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <x-admin.form-input name="title_km" label="ចំណងជើងខ្មែរ" :value="$slide?->title_km" required />
    <x-admin.form-input name="title_en" label="ចំណងជើងអង់គ្លេស" :value="$slide?->title_en" />
</div>

<x-admin.textarea name="description" label="ពិពណ៌នា" :value="$slide?->description" rows="3" />

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <x-admin.form-input name="button_text" label="Button Text" :value="$slide?->button_text" />
    <x-admin.form-input name="button_link" label="Button Link" :value="$slide?->button_link" />
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <x-admin.form-input name="sort_order" label="លំដាប់" type="number" :value="$slide?->sort_order ?? 0" />
    <x-admin.form-input name="published_at" label="កាលបរិច្ឆេទផ្សព្វផ្សាយ" type="datetime-local" :value="$slide?->published_at?->format('Y-m-d\TH:i')" />
</div>

<x-admin.upload-field name="image" label="រូបភាព" :current="$slide?->image" />

<label class="flex items-center gap-2 text-[13px] text-ink">
    <input type="checkbox" name="is_active" value="1" @checked($slide ? $slide->is_active : true) class="rounded border-line">
    បើក (បង្ហាញនៅលើទំព័រដើម)
</label>
