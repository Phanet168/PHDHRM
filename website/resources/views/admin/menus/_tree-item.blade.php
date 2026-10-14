<li class="menu-tree-item border border-line rounded-[8px] bg-white" data-id="{{ $item->id }}">
    <div class="flex items-center gap-3 px-3 py-[10px]">
        <span class="menu-drag-handle cursor-move text-muted select-none">⠿</span>
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                @if ($item->icon)<span class="text-[13px]">{{ $item->icon }}</span>@endif
                <p class="font-bold text-ink text-[13px] truncate">{{ $item->label_km }}</p>
                @if ($item->label_en)<span class="text-muted text-[11px]">({{ $item->label_en }})</span>@endif
                @if (! $item->is_active)
                    <span class="bg-gray-100 text-gray-500 text-[10px] font-bold rounded-full px-2 py-[2px]">បិទ</span>
                @endif
            </div>
            <p class="text-muted text-[11px] truncate">{{ $item->linkSummary() }}</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <form method="POST" action="{{ route('admin.menus.items.toggle', [$menu, $item]) }}">
                @csrf
                <button type="submit" class="text-[11px] font-bold {{ $item->is_active ? 'text-muted' : 'text-brand-green' }}">
                    {{ $item->is_active ? 'បិទ' : 'បើក' }}
                </button>
            </form>
            <a href="{{ route('admin.menus.items.edit', [$menu, $item]) }}" class="text-brand-blue text-[12px] font-bold">កែប្រែ</a>
            <form method="POST" action="{{ route('admin.menus.items.destroy', [$menu, $item]) }}" onsubmit="return confirm('លុបធាតុនេះ?')">
                @csrf @method('DELETE')
                <button type="submit" class="text-brand-red text-[12px] font-bold">លុប</button>
            </form>
        </div>
    </div>

    <ul class="menu-tree flex flex-col gap-2 pl-8 pb-2 pr-3" data-parent-id="{{ $item->id }}">
        @foreach ($item->children as $child)
            @include('admin.menus._tree-item', ['item' => $child, 'menu' => $menu])
        @endforeach
    </ul>
</li>
