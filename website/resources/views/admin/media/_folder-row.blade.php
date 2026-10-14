@php
    $hasContents = $folder->media_count > 0 || $folder->children_count > 0;
    $confirmMessage = $hasContents
        ? 'ថតនេះមានរូបភាព ឬថតរងនៅខាងក្នុង។ តើអ្នកចង់ផ្លាស់ទីវា និងខ្លឹមសាររបស់វាទាំងអស់ទៅធុងសម្រាមឬទេ?'
        : 'លុបថតនេះ?';
    $key = 'folder:' . $folder->id;
    $openUrl = route('admin.media.index', ['folder' => $folder->id]);
@endphp

<tr draggable="true" data-select-key="{{ $key }}"
    @dragstart="dragStart($event, 'folder', {{ $folder->id }})"
    @dragover="dragOverFolder($event, {{ $folder->id }})" @dragleave="dragLeaveFolder({{ $folder->id }})" @drop="dropOnFolder($event, {{ $folder->id }})"
    @click="handleItemClick($event, 'folder', {{ $folder->id }})"
    @dblclick="openItem('folder', {{ $folder->id }}, '{{ $openUrl }}')"
    @contextmenu="openContextMenu($event, 'folder', {{ $folder->id }})"
    :class="{ 'is-selected': isSelected('folder', {{ $folder->id }}), 'is-drop-target': isDropTarget({{ $folder->id }}) }">
    <td><input type="checkbox" :checked="isSelected('folder', {{ $folder->id }})" @click.stop @change="toggleSelect('folder', {{ $folder->id }})"></td>
    <td>
        <div class="flex items-center gap-3">
            <div class="media-list-thumb"><x-media-folder-icon :size="22" /></div>
            <span class="font-bold text-ink">{{ $folder->name }}</span>
        </div>
    </td>
    <td>ថត</td>
    <td>—</td>
    <td>{{ $folder->media_count }} ឯកសារ</td>
    <td>{{ $folder->updated_at->format('d-m-Y') }}</td>
    <td>{{ $folder->creator?->name ?? '—' }}</td>
    <td>
        <div class="flex items-center gap-3" style="position:relative;">
            @can('update', $folder)
                <button type="button" class="text-brand-blue text-[12px] font-bold" @click.stop="window.dispatchEvent(new CustomEvent('open-modal-rename-folder-{{ $folder->id }}'))">ប្តូរឈ្មោះ</button>
            @endcan
            @can('move', $folder)
                <button type="button" class="text-ink text-[12px] font-bold" @click.stop="window.dispatchEvent(new CustomEvent('open-modal-move-folder-{{ $folder->id }}'))">ផ្លាស់ទី</button>
            @endcan
            @can('delete', $folder)
                <form method="POST" action="{{ route('admin.media.folders.destroy', $folder) }}" onsubmit="return confirm('{{ $confirmMessage }}')">
                    @csrf @method('DELETE')
                    <input type="hidden" name="cascade" value="1">
                    <button type="submit" class="text-brand-red text-[12px] font-bold" @click.stop>លុប</button>
                </form>
            @endcan
        </div>
    </td>
</tr>

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
