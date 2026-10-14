@php $category = $category ?? null; @endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <x-admin.form-input name="name_km" label="ឈ្មោះប្រភេទ (ខ្មែរ)" :value="$category?->name_km" required />
    <x-admin.form-input name="name_en" label="ឈ្មោះប្រភេទ (អង់គ្លេស)" :value="$category?->name_en" />
    <x-admin.form-input name="slug" label="Slug" :value="$category?->slug" required />
    <x-admin.select name="parent_id" label="ប្រភេទមេ (Parent)" :value="$category?->parent_id" :options="$parents->pluck('name_km', 'id')" placeholder="— គ្មាន (កម្រិតកំពូល) —" />
</div>

<x-admin.textarea name="description" label="ការពិពណ៌នា" :value="$category?->description" rows="3" />

<div class="flex items-center gap-6">
    <label class="flex items-center gap-2 text-[13px] text-ink">
        <input type="checkbox" name="is_active" value="1" @checked($category ? $category->is_active : true) class="rounded border-line">
        សកម្ម (បង្ហាញនៅលើគេហទំព័រ)
    </label>
    <div class="w-[140px]">
        <x-admin.form-input name="sort_order" label="លំដាប់" type="number" :value="$category?->sort_order ?? 0" />
    </div>
</div>
