@php $document = $document ?? null; @endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <x-admin.form-input name="title" label="ចំណងជើង" :value="$document?->title" required />
    <x-admin.select name="category_id" label="ប្រភេទលិខិត" :value="$document?->category_id" :options="$categories->pluck('name', 'id')" />
    {{-- Records imported from an external link may have no known number/date/year; those stay optional for them. --}}
    <x-admin.form-input name="document_number" label="លេខលិខិត" :value="$document?->document_number" :required="! $document || $document->document_number !== null" />
    <x-admin.form-input name="issuing_authority" label="ស្ថាប័នចេញ" :value="$document?->issuing_authority" required />
    <x-admin.form-input name="issue_date" label="កាលបរិច្ឆេទ" type="date" :value="$document?->issue_date?->format('Y-m-d')" :required="! $document || $document->issue_date !== null" />
    <x-admin.form-input name="year" label="ឆ្នាំ" type="number" :value="$document ? $document->year : now()->year" :required="! $document || $document->year !== null" />
</div>

<x-admin.textarea name="description" label="ការពិពណ៌នា" :value="$document?->description" rows="3" />

<div>
    <x-admin.upload-field name="file" label="ឯកសារ PDF" accept="application/pdf" />
    @if ($document?->file_path)
        <p class="text-muted text-[12px] mt-1">ឯកសារបច្ចុប្បន្ន៖ {{ basename($document->file_path) }}</p>
    @endif
</div>

<div>
    <x-admin.form-input name="external_url" label="ឬ តំណទាញយកខាងក្រៅ (ឧ. Google Drive)" type="url" :value="$document?->external_url" placeholder="https://..." />
    <p class="text-muted text-[12px] mt-1">ប្រើតំណនេះជំនួសការ Upload ។ ប្រសិនបើមានទាំងពីរ ឯកសារដែល Upload ត្រូវបានប្រើមុន។</p>
</div>
