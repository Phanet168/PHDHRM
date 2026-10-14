@php $key = 'media:' . $item->id; @endphp

<tr draggable="true" data-select-key="{{ $key }}" data-size-bytes="{{ $item->size }}"
    @dragstart="dragStart($event, 'media', {{ $item->id }})"
    @click="handleItemClick($event, 'media', {{ $item->id }})"
    @dblclick="openItem('media', {{ $item->id }}, '{{ $item->url }}')"
    @contextmenu="openContextMenu($event, 'media', {{ $item->id }})"
    :class="isSelected('media', {{ $item->id }}) ? 'is-selected' : ''">
    <td><input type="checkbox" :checked="isSelected('media', {{ $item->id }})" @click.stop @change="toggleSelect('media', {{ $item->id }})"></td>
    <td>
        <div class="flex items-center gap-3">
            <div class="media-list-thumb">
                @if ($item->isImage() && ! $item->fileMissing())
                    <img src="{{ $item->url }}" alt="{{ $item->alt_text ?? $item->original_filename }}" loading="lazy">
                @else
                    <x-media-file-icon :type="$item->iconType()" :size="22" />
                @endif
            </div>
            <span class="font-bold text-ink truncate max-w-[220px]">{{ $item->original_filename }}</span>
            @if ($item->fileMissing())
                <span class="media-missing-badge" title="រកមិនឃើញឯកសារនេះនៅលើឌីសទេ">ឯកសារបាត់</span>
            @endif
        </div>
    </td>
    <td>{{ $item->typeLabel() }}</td>
    <td>{{ $item->dimensions() ?? '—' }}</td>
    <td>{{ $item->formattedSize() }}</td>
    <td>{{ $item->folder?->name ?? 'Media' }}</td>
    <td>
        @if ($isUsed ?? false)
            <span class="media-usage-badge is-used">កំពុងប្រើ</span>
        @else
            <span class="media-usage-badge is-unused">មិនប្រើ</span>
        @endif
    </td>
    <td>{{ $item->updated_at->format('d-m-Y') }}</td>
    <td>{{ $item->uploader?->name ?? '—' }}</td>
    <td>
        <div class="flex items-center gap-3">
            <button type="button" class="text-brand-blue text-[12px] font-bold" @click.stop="openPreview({{ $item->id }})">មើល</button>
            <a href="{{ $item->url }}" download="{{ $item->original_filename }}" class="text-brand-blue text-[12px] font-bold" @click.stop>ទាញយក</a>
            @can('update', $item)
                <button type="button" class="text-brand-blue text-[12px] font-bold" @click.stop="window.dispatchEvent(new CustomEvent('open-modal-edit-media-{{ $item->id }}'))">កែ</button>
            @endcan
            @can('move', $item)
                <button type="button" class="text-ink text-[12px] font-bold" @click.stop="window.dispatchEvent(new CustomEvent('open-modal-move-media-{{ $item->id }}'))">ផ្លាស់ទី</button>
            @endcan
            @can('delete', $item)
                <form method="POST" action="{{ route('admin.media.destroy', $item) }}" onsubmit="return confirm('លុបឯកសារនេះ?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-brand-red text-[12px] font-bold" @click.stop>លុប</button>
                </form>
            @endcan
        </div>
    </td>
</tr>

@include('admin.media._media-card-modals', ['item' => $item])
