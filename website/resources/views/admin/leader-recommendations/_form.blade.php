@php $recommendation = $recommendation ?? null; @endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <x-admin.form-input name="title_km" label="ចំណងជើងផ្នែក (ខ្មែរ)" :value="$recommendation?->title_km" placeholder="អនុសាសន៍ថ្នាក់ដឹកនាំ" required />
    <x-admin.form-input name="subtitle_km" label="ចំណងជើងរង (ស្រេចចិត្ត)" :value="$recommendation?->subtitle_km" />
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <x-admin.form-input name="leader_name_km" label="ឈ្មោះថ្នាក់ដឹកនាំ" :value="$recommendation?->leader_name_km" required />
    <x-admin.form-input name="leader_position_km" label="តួនាទី (ស្រេចចិត្ត)" :value="$recommendation?->leader_position_km" />
</div>

<x-admin.form-input name="published_at" label="កាលបរិច្ឆេទផ្សព្វផ្សាយ (ស្រេចចិត្ត)" type="datetime-local" :value="$recommendation?->published_at?->format('Y-m-d\TH:i')" />

<label class="flex items-center gap-2 text-[13px] text-ink">
    <input type="checkbox" name="is_active" value="1" @checked($recommendation ? $recommendation->is_active : true) class="rounded border-line">
    បើក (បង្ហាញនៅលើទំព័រដើម)
</label>
