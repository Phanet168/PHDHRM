@can('update', $item)
    <x-admin.modal name="edit-media-{{ $item->id }}" title="ព័ត៌មានឯកសារ">
        <div class="flex flex-col gap-3">
            @if ($item->isImage())
                <img src="{{ $item->url }}" alt="{{ $item->alt_text }}" class="w-full max-h-[260px] object-contain rounded-[8px] bg-surface">
            @endif
            <p class="text-muted text-[12px]">{{ $item->original_filename }} · {{ $item->dimensions() }} · {{ $item->formattedSize() }}</p>
            <form method="POST" action="{{ route('admin.media.update', $item) }}" class="flex flex-col gap-3">
                @csrf @method('PUT')
                <x-admin.form-input name="alt_text" label="អត្ថបទជំនួស (Alt Text)" :value="$item->alt_text" />
                <x-admin.textarea name="caption" label="ចំណងជើងរូបភាព (Caption)" :value="$item->caption" rows="2" />
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="open = false" class="text-muted text-[13px] font-bold px-3">បិទ</button>
                    <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-10 px-5">រក្សាទុក</button>
                </div>
            </form>
        </div>
    </x-admin.modal>

    <x-admin.modal name="rename-media-{{ $item->id }}" title="ប្តូរឈ្មោះឯកសារ">
        <form method="POST" action="{{ route('admin.media.rename', $item) }}" class="flex flex-col gap-3">
            @csrf
            <x-admin.form-input name="name" label="ឈ្មោះឯកសារ" :value="$item->original_filename" required />
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="open = false" class="text-muted text-[13px] font-bold px-3">បោះបង់</button>
                <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-10 px-5">រក្សាទុក</button>
            </div>
        </form>
    </x-admin.modal>

    <x-admin.modal name="replace-media-{{ $item->id }}" title="ជំនួសឯកសារ">
        <form method="POST" action="{{ route('admin.media.replace', $item) }}" enctype="multipart/form-data" class="flex flex-col gap-3">
            @csrf
            <p class="text-muted text-[12px]">ឯកសារថ្មីនឹងជំនួសឯកសារនេះ ដោយរក្សា URL ដដែល។ គ្រប់កន្លែងប្រើប្រាស់ឯកសារនេះនឹងបានធ្វើបច្ចុប្បន្នភាពដោយស្វ័យប្រវត្តិ។</p>
            <x-admin.upload-field name="file" label="ជ្រើសរើសឯកសារថ្មី" accept="image/*,application/pdf" />
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="open = false" class="text-muted text-[13px] font-bold px-3">បោះបង់</button>
                <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-10 px-5">ជំនួស</button>
            </div>
        </form>
    </x-admin.modal>
@endcan

@can('move', $item)
    <x-admin.modal name="move-media-{{ $item->id }}" title="ផ្លាស់ទីឯកសារ">
        <form method="POST" action="{{ route('admin.media.move', $item) }}" class="flex flex-col gap-3">
            @csrf
            <x-admin.select name="folder_id" label="ថតគោលដៅ" :options="\App\Models\MediaFolder::optionsTree()" placeholder="— Media (ថតគល់) —" />
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="open = false" class="text-muted text-[13px] font-bold px-3">បោះបង់</button>
                <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-10 px-5">ផ្លាស់ទី</button>
            </div>
        </form>
    </x-admin.modal>
@endcan
