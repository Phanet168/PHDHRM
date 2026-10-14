@php
    $link = $link ?? null;
    $icons = ['pin', 'categories', 'document', 'heart', 'phone', 'user', 'users', 'hospital', 'shield', 'monitor', 'globe', 'stethoscope', 'heart-pulse', 'trending-up', 'link', 'check'];
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <x-admin.form-input name="title_km" label="ចំណងជើងកាត (ខ្មែរ)" :value="$link?->title_km" placeholder="មូលដ្ឋានសុខាភិបាល" required />
    <x-admin.form-input name="description_km" label="ការពិពណ៌នាខ្លី (ស្រេចចិត្ត)" :value="$link?->description_km" />
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <x-admin.select name="icon" label="រូបតំណាង (Icon)" :options="array_combine($icons, $icons)" :value="$link?->icon" required />
    <x-admin.form-input name="sort_order" label="លំដាប់តម្រៀប" type="number" min="0" :value="$link?->sort_order ?? 0" />
</div>

<div x-data="{ linkType: '{{ old('link_type', $link?->link_type ?? 'route') }}' }" class="flex flex-col gap-4">
    <div class="flex items-center gap-6">
        <label class="flex items-center gap-2 text-[13px] text-ink">
            <input type="radio" name="link_type" value="route" x-model="linkType" class="rounded border-line">
            តំណភ្ជាប់ក្នុងគេហទំព័រ (Route)
        </label>
        <label class="flex items-center gap-2 text-[13px] text-ink">
            <input type="radio" name="link_type" value="url" x-model="linkType" class="rounded border-line">
            URL ខាងក្រៅ
        </label>
    </div>

    <div x-show="linkType === 'route'" x-cloak>
        <x-admin.select name="route_name" label="ឈ្មោះ Route" :value="$link?->route_name" :options="[
            'facilities.index' => 'facilities.index — មូលដ្ឋានសុខាភិបាល',
            'news.index' => 'news.index — ព័ត៌មាន និងសកម្មភាព',
            'legal-documents.index' => 'legal-documents.index — លិខិតបទដ្ឋានគតិយុត្ត',
            'health-content.index' => 'health-content.index — មាតិកាអប់រំសុខភាព',
            'about' => 'about — អំពីមន្ទីរ',
            'contact' => 'contact — ទំនាក់ទំនង',
        ]" />
    </div>
    <div x-show="linkType === 'url'" x-cloak>
        <x-admin.form-input name="url" label="URL" :value="$link?->url" placeholder="https://..." />
    </div>
</div>

<label class="flex items-center gap-2 text-[13px] text-ink">
    <input type="checkbox" name="is_active" value="1" @checked($link ? $link->is_active : true) class="rounded border-line">
    បើក (បង្ហាញនៅលើទំព័រដើម)
</label>
