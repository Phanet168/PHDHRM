@php
    $showReferenceWarning = session('confirm_delete_media') == $item->id;
    $key = 'media:' . $item->id;
@endphp

<div class="media-file-card" draggable="true" data-select-key="{{ $key }}" data-size-bytes="{{ $item->size }}"
     @dragstart="dragStart($event, 'media', {{ $item->id }})"
     @click="handleItemClick($event, 'media', {{ $item->id }})"
     @dblclick="openItem('media', {{ $item->id }}, '{{ $item->url }}')"
     @contextmenu="openContextMenu($event, 'media', {{ $item->id }})"
     :class="{ 'is-selected': isSelected('media', {{ $item->id }}), 'is-cut': isCut('media', {{ $item->id }}) }">
    <input type="checkbox" class="media-select-checkbox" :checked="isSelected('media', {{ $item->id }})" @click.stop @change="toggleSelect('media', {{ $item->id }})">
    <div class="media-check-badge"><x-news-icon name="check" width="12" height="12" /></div>

    <div class="media-card-menu">
        <button type="button" class="media-card-menu-btn" @click.stop="toggleMenu('{{ $key }}')" aria-label="ជម្រើសឯកសារ">⋮</button>
        <div class="media-card-menu-list" data-menu-for="{{ $key }}" x-show="openMenuKey === '{{ $key }}'" x-cloak @click.outside="closeMenu()" x-transition>
            <button type="button" @click="openPreview({{ $item->id }}); closeMenu()">មើល (Preview)</button>
            <a href="{{ $item->url }}" download="{{ $item->original_filename }}">ទាញយក</a>
            <button type="button" @click="copyUrl('{{ $item->url }}'); closeMenu()">ចម្លង URL</button>
            <hr>
            @can('copy', $item)
                <button type="button" @click="cutSelected(); closeMenu()">កាត់</button>
                <button type="button" @click="copySelected(); closeMenu()">ចម្លង</button>
            @endcan
            @can('move', $item)
                <button type="button" @click="window.dispatchEvent(new CustomEvent('open-modal-move-media-{{ $item->id }}')); closeMenu()">ផ្លាស់ទីទៅ...</button>
            @endcan
            <hr>
            @can('update', $item)
                <button type="button" @click="window.dispatchEvent(new CustomEvent('open-modal-rename-media-{{ $item->id }}')); closeMenu()">ប្តូរឈ្មោះ</button>
                <button type="button" @click="window.dispatchEvent(new CustomEvent('open-modal-replace-media-{{ $item->id }}')); closeMenu()">ជំនួសឯកសារ</button>
            @endcan
            @can('copy', $item)
                <form method="POST" action="{{ route('admin.media.duplicate', $item) }}">
                    @csrf
                    <button type="submit">ចម្លងឯកសារ</button>
                </form>
            @endcan
            @can('delete', $item)
                <hr>
                <form method="POST" action="{{ route('admin.media.destroy', $item) }}" onsubmit="return confirm('លុបឯកសារនេះ?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="is-danger">លុប</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="media-file-thumb {{ $item->mime_type === 'image/png' ? 'is-graphic' : '' }}">
        @if ($item->isImage() && ! $item->fileMissing())
            <img src="{{ $item->url }}" alt="{{ $item->alt_text ?? $item->original_filename }}" loading="lazy">
        @elseif ($item->isVideo() && ! $item->fileMissing())
            <div class="media-file-icon-wrap"><x-media-file-icon type="video" :size="36" /></div>
            <div class="media-file-play-badge"><x-news-icon name="play" width="22" height="22" style="color:white" /></div>
        @else
            <div class="media-file-icon-wrap"><x-media-file-icon :type="$item->iconType()" :size="36" /></div>
        @endif
        @if ($item->fileMissing())
            <div class="media-missing-badge" title="រកមិនឃើញឯកសារនេះនៅលើឌីសទេ">ឯកសារបាត់</div>
        @endif
    </div>
    <div class="media-file-body">
        <p class="media-file-name" title="{{ $item->original_filename }}">{{ $item->original_filename }}</p>
        <p class="media-file-meta">{{ $item->dimensions() ?? $item->typeLabel() }} · {{ $item->formattedSize() }}</p>
        @if ($isUsed ?? false)
            <span class="media-usage-badge is-used">កំពុងប្រើ</span>
        @else
            <span class="media-usage-badge is-unused">មិនប្រើ</span>
        @endif
    </div>

    @if ($showReferenceWarning)
        <div class="media-reference-warning">
            <p class="font-bold">ឯកសារនេះកំពុងត្រូវបានប្រើប្រាស់</p>
            @if (session('media_references'))
                <ul>
                    @foreach (session('media_references') as $reference)
                        <li>{{ $reference['label'] }} ({{ $reference['count'] }})</li>
                    @endforeach
                </ul>
            @endif
            <form method="POST" action="{{ route('admin.media.destroy', $item) }}" onsubmit="return confirm('ឯកសារនេះកំពុងប្រើប្រាស់នៅកន្លែងផ្សេង។ តើអ្នកចង់លុបទាំងអស់ក៏ដោយឬទេ?')" class="mt-2">
                @csrf @method('DELETE')
                <input type="hidden" name="force" value="1">
                <button type="submit" class="text-brand-red text-[11px] font-bold">លុបទាំងអស់ក៏ដោយ</button>
            </form>
        </div>
    @endif
</div>
