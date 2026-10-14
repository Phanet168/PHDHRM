@php
    $isActive = $currentFolderId === $folder->id;
    $isAncestor = $currentFolderId && in_array($currentFolderId, $folder->descendantIds(), true);
@endphp

<div>
    <div class="media-tree-node" x-data="{ open: {{ $isAncestor || $isActive ? 'true' : 'false' }} }">
        @if ($folder->allChildren->isNotEmpty())
            <button type="button" class="media-tree-toggle" @click="open = !open" :aria-expanded="open" aria-label="បើក/បិទ">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" :class="open ? 'rotate-90' : ''" style="transition: transform .15s;">
                    <polyline points="9 6 15 12 9 18"></polyline>
                </svg>
            </button>
        @else
            <span class="media-tree-toggle"></span>
        @endif

        <a href="{{ route('admin.media.index', ['folder' => $folder->id]) }}" class="media-tree-link {{ $isActive ? 'is-active' : '' }}"
           draggable="true" @dragstart="dragStart($event, 'folder', {{ $folder->id }})"
           :class="isDropTarget({{ $folder->id }}) ? 'is-drop-target' : ''"
           @dragover="dragOverFolder($event, {{ $folder->id }})" @dragleave="dragLeaveFolder({{ $folder->id }})" @drop="dropOnFolder($event, {{ $folder->id }})">
            <x-media-folder-icon :size="15" class="media-tree-folder-icon" /> {{ $folder->name }}
        </a>

        @if ($folder->allChildren->isNotEmpty())
            <div class="media-tree-children" x-show="open" x-cloak>
                @foreach ($folder->allChildren as $child)
                    @include('admin.media._folder-tree-item', ['folder' => $child, 'currentFolderId' => $currentFolderId])
                @endforeach
            </div>
        @endif
    </div>
</div>
