@php
    $hasContents = $folder->media_count > 0 || $folder->children_count > 0;
    $confirmMessage = $hasContents
        ? 'ថតនេះមានរូបភាព ឬថតរងនៅខាងក្នុង។ តើអ្នកចង់ផ្លាស់ទីវា និងខ្លឹមសាររបស់វាទាំងអស់ទៅធុងសម្រាមឬទេ?'
        : 'លុបថតនេះ?';
    $key = 'folder:' . $folder->id;
    $openUrl = route('admin.media.index', ['folder' => $folder->id]);
@endphp

<div class="media-folder-card" draggable="true" data-select-key="{{ $key }}"
     @dragstart="dragStart($event, 'folder', {{ $folder->id }})"
     @dragover="dragOverFolder($event, {{ $folder->id }})" @dragleave="dragLeaveFolder({{ $folder->id }})" @drop="dropOnFolder($event, {{ $folder->id }})"
     @click="handleItemClick($event, 'folder', {{ $folder->id }})"
     @dblclick="openItem('folder', {{ $folder->id }}, '{{ $openUrl }}')"
     @contextmenu="openContextMenu($event, 'folder', {{ $folder->id }})"
     :class="{ 'is-selected': isSelected('folder', {{ $folder->id }}), 'is-drop-target': isDropTarget({{ $folder->id }}), 'is-cut': isCut('folder', {{ $folder->id }}) }">
    <input type="checkbox" class="media-select-checkbox" :checked="isSelected('folder', {{ $folder->id }})" @click.stop @change="toggleSelect('folder', {{ $folder->id }})">
    <div class="media-check-badge"><x-news-icon name="check" width="12" height="12" /></div>

    <div class="media-card-menu">
        <button type="button" class="media-card-menu-btn" @click.stop="toggleMenu('{{ $key }}')" aria-label="ជម្រើសថត">⋮</button>
        <div class="media-card-menu-list" data-menu-for="{{ $key }}" x-show="openMenuKey === '{{ $key }}'" x-cloak @click.outside="closeMenu()" x-transition>
            <a href="{{ $openUrl }}">បើក</a>
            <hr>
            @can('copy', $folder)
                <button type="button" @click="cutSelected(); closeMenu()">កាត់</button>
                <button type="button" @click="copySelected(); closeMenu()">ចម្លង</button>
            @endcan
            @can('move', $folder)
                <button type="button" @click="window.dispatchEvent(new CustomEvent('open-modal-move-folder-{{ $folder->id }}')); closeMenu()">ផ្លាស់ទីទៅ...</button>
            @endcan
            <hr>
            @can('update', $folder)
                <button type="button" @click="window.dispatchEvent(new CustomEvent('open-modal-rename-folder-{{ $folder->id }}')); closeMenu()">ប្តូរឈ្មោះ</button>
            @endcan
            @can('delete', $folder)
                <form method="POST" action="{{ route('admin.media.folders.destroy', $folder) }}" onsubmit="return confirm('{{ $confirmMessage }}')">
                    @csrf @method('DELETE')
                    <input type="hidden" name="cascade" value="1">
                    <button type="submit" class="is-danger">លុប</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="media-folder-open">
        <x-media-folder-icon :size="40" class="media-folder-icon-svg" />
        <span class="media-folder-name">{{ $folder->name }}</span>
        <span class="media-folder-count">
            {{ $folder->media_count }} ឯកសារ
            @if ($folder->children_count > 0)
                · {{ $folder->children_count }} ថតរង
            @endif
        </span>
    </div>
</div>
