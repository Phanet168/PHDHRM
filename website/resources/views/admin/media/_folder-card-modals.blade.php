@can('update', $folder)
    <x-admin.modal name="rename-folder-{{ $folder->id }}" title="ប្តូរឈ្មោះថត">
        <form method="POST" action="{{ route('admin.media.folders.update', $folder) }}" class="flex flex-col gap-3">
            @csrf @method('PUT')
            <x-admin.form-input name="name" label="ឈ្មោះថត" :value="$folder->name" required />
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="open = false" class="text-muted text-[13px] font-bold px-3">បោះបង់</button>
                <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-10 px-5">រក្សាទុក</button>
            </div>
        </form>
    </x-admin.modal>
@endcan

@can('move', $folder)
    <x-admin.modal name="move-folder-{{ $folder->id }}" title="ផ្លាស់ទីថត">
        <form method="POST" action="{{ route('admin.media.folders.move', $folder) }}" class="flex flex-col gap-3">
            @csrf
            <x-admin.select name="parent_id" label="ថតគោលដៅ" :options="\App\Models\MediaFolder::optionsTree($folder->descendantIds())" placeholder="— Media (ថតគល់) —" />
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="open = false" class="text-muted text-[13px] font-bold px-3">បោះបង់</button>
                <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-10 px-5">ផ្លាស់ទី</button>
            </div>
        </form>
    </x-admin.modal>
@endcan
