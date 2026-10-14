@php
    $item = $item ?? null;
    $publicRoutes = [
        'home' => 'ទំព័រដើម (home)',
        'facilities.index' => 'មូលដ្ឋានសុខាភិបាល (facilities.index)',
        'news.index' => 'ព័ត៌មាន និងសកម្មភាព (news.index)',
        'legal-documents.index' => 'លិខិតបទដ្ឋានគតិយុត្ត (legal-documents.index)',
        'health-content.index' => 'មាតិកាអប់រំសុខភាព (health-content.index)',
        'about' => 'អំពីមន្ទីរ (about)',
        'contact' => 'ទំនាក់ទំនង (contact)',
    ];
@endphp

<div x-data="{ linkType: '{{ old('link_type', $item?->link_type ?? 'route') }}' }" class="flex flex-col gap-4">
    <x-admin.select name="parent_id" label="ម៉ឺនុយមេ (Parent)" :value="$item?->parent_id" :options="$parents->pluck('label_km', 'id')" placeholder="— គ្មាន (កម្រិតកំពូល) —" />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <x-admin.form-input name="label_km" label="ស្លាកឈ្មោះ (ខ្មែរ)" :value="$item?->label_km" required />
        <x-admin.form-input name="label_en" label="ស្លាកឈ្មោះ (អង់គ្លេស)" :value="$item?->label_en" />
    </div>

    <x-admin.form-input name="icon" label="Icon (តួអក្សរ ឬសញ្ញា ១) — ស្រេចចិត្ត" :value="$item?->icon" />

    <div>
        <label class="block text-ink text-[13px] font-bold mb-1">ប្រភេទតំណ (Link Type)</label>
        <select name="link_type" x-model="linkType" class="bg-white border border-line rounded-[8px] h-[44px] px-[14px] text-[14px] text-ink w-full">
            <option value="route" @selected(($item?->link_type ?? 'route') === 'route')>ផ្លូវខាងក្នុង (Internal Route)</option>
            <option value="url" @selected($item?->link_type === 'url')>URL ផ្ទាល់ខ្លួន</option>
            <option value="page" @selected($item?->link_type === 'page')>ទំព័រ CMS (Page)</option>
            <option value="facility" @selected($item?->link_type === 'facility')>មូលដ្ឋានសុខាភិបាល (Facility)</option>
            <option value="news_category" @selected($item?->link_type === 'news_category')>ប្រភេទព័ត៌មាន (News Category)</option>
            <option value="content_category" @selected($item?->link_type === 'content_category')>ប្រភេទមាតិកា (Content Category)</option>
            <option value="content" @selected($item?->link_type === 'content')>អត្ថបទ (Content Article)</option>
        </select>
    </div>

    <div x-show="linkType === 'route'">
        <x-admin.select name="route_name" label="ជ្រើសរើសផ្លូវ" :value="$item?->route_name" :options="$publicRoutes" />
    </div>

    <div x-show="linkType === 'url'">
        <x-admin.form-input name="url" label="URL (ឧ. https://example.com ឬ /facilities)" :value="$item?->url" />
    </div>

    <div x-show="linkType === 'page'">
        <x-admin.select name="linkable_id" label="ជ្រើសរើសទំព័រ" :value="$item?->link_type === 'page' ? $item?->linkable_id : null" :options="$pages->pluck('title_km', 'id')" />
    </div>

    <div x-show="linkType === 'facility'">
        <x-admin.select name="linkable_id" label="ជ្រើសរើសមូលដ្ឋាន" :value="$item?->link_type === 'facility' ? $item?->linkable_id : null" :options="$facilities->pluck('name_km', 'id')" />
    </div>

    <div x-show="linkType === 'news_category'">
        <x-admin.select name="linkable_id" label="ជ្រើសរើសប្រភេទព័ត៌មាន" :value="$item?->link_type === 'news_category' ? $item?->linkable_id : null" :options="$newsCategories->pluck('name_km', 'id')" />
    </div>

    <div x-show="linkType === 'content_category'">
        <x-admin.select name="linkable_id" label="ជ្រើសរើសប្រភេទមាតិកា" :value="$item?->link_type === 'content_category' ? $item?->linkable_id : null" :options="$contentCategories->pluck('name_km', 'id')" />
    </div>

    <div x-show="linkType === 'content'">
        <x-admin.select name="linkable_id" label="ជ្រើសរើសអត្ថបទ" :value="$item?->link_type === 'content' ? $item?->linkable_id : null" :options="$contents->pluck('title_km', 'id')" />
    </div>

    <x-admin.select name="target" label="បើកនៅ (Target)" :value="$item?->target ?? '_self'" :options="['_self' => 'ទំព័រដដែល (_self)', '_blank' => 'ផ្ទាំងថ្មី (_blank)']" :placeholder="null" />

    <label class="flex items-center gap-2 text-[13px] text-ink">
        <input type="checkbox" name="is_active" value="1" @checked($item ? $item->is_active : true) class="rounded border-line">
        បើក (បង្ហាញនៅលើម៉ឺនុយ)
    </label>
</div>
