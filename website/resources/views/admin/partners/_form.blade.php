@php $partner = $partner ?? null; @endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <x-admin.form-input name="name" label="ឈ្មោះ" :value="$partner?->name" required />
    <x-admin.form-input name="website" label="គេហទំព័រ" :value="$partner?->website" />
    <x-admin.form-input name="sort_order" label="លំដាប់" type="number" :value="$partner?->sort_order ?? 0" />
</div>

<x-admin.textarea name="description" label="ការពិពណ៌នា" :value="$partner?->description" rows="3" />
<x-admin.upload-field name="logo" label="រូបសញ្ញា" :current="$partner?->logo" />

<label class="flex items-center gap-2 text-[13px] text-ink">
    <input type="checkbox" name="active" value="1" @checked($partner ? $partner->active : true) class="rounded border-line">
    សកម្ម (បង្ហាញនៅលើគេហទំព័រ)
</label>
