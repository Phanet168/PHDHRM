@php $message = $message ?? null; @endphp

<x-admin.textarea name="message_km" label="សារ (ខ្មែរ)" :value="$message?->message_km" rows="2" required />

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <x-admin.select name="icon" label="រូបតំណាង" :value="$message?->icon" :options="\App\Support\HealthMessageIcon::options()" required />
    <x-admin.form-input name="accent_color" label="ពណ៌សង្កត់ធ្ងន់ (hex, ស្រេចចិត្ត)" :value="$message?->accent_color" placeholder="#22C55E" />
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <x-admin.form-input name="source_name" label="ប្រភព (ស្រេចចិត្ត)" :value="$message?->source_name ?? 'ក្រសួងសុខាភិបាល'" />
    <x-admin.form-input name="source_url" label="តំណភ្ជាប់ប្រភព (ស្រេចចិត្ត)" :value="$message?->source_url" placeholder="https://moh.gov.kh/..." />
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <x-admin.form-input type="number" name="sort_order" label="លំដាប់" :value="$message?->sort_order ?? 0" />
    <label class="flex items-center gap-2 text-[13px] font-bold text-ink self-end pb-2">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $message?->is_active ?? true))>
        សកម្ម (បង្ហាញនៅលើគេហទំព័រ)
    </label>
</div>
