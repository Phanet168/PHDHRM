<x-admin.layout :title="$menu->name">
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center gap-3">
            <p class="font-bold text-brand-navy text-[18px]">{{ $menu->name }}</p>
            <span class="{{ $menu->is_active ? 'bg-brand-green-tint text-brand-green' : 'bg-gray-100 text-gray-500' }} inline-flex rounded-full px-3 py-1 text-[12px] font-bold">
                {{ $menu->is_active ? 'បានផ្សាយ' : 'សេចក្តីព្រាង' }}
            </span>
        </div>
        <a href="{{ route('admin.menus.items.create', $menu) }}" class="bg-brand-green text-white font-bold text-[13px] rounded-[8px] h-10 px-5 flex items-center">+ បន្ថែមធាតុម៉ឺនុយ</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 flex flex-col gap-6">
            <form method="POST" action="{{ route('admin.menus.update', $menu) }}" class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-5 flex flex-col gap-3">
                @csrf @method('PUT')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <x-admin.form-input name="name" label="ឈ្មោះម៉ឺនុយ" :value="$menu->name" required />
                    <x-admin.form-input name="slug" label="Slug" :value="$menu->slug" required />
                </div>
                <label class="flex items-center gap-2 text-[13px] text-ink">
                    <input type="checkbox" name="is_active" value="1" @checked($menu->is_active) class="rounded border-line">
                    ផ្សាយ (បង្ហាញនៅលើគេហទំព័រ)
                </label>
                <div class="flex justify-end">
                    <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-10 px-5">រក្សាទុកការកំណត់</button>
                </div>
            </form>

            <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-5">
                <p class="font-bold text-brand-navy text-[15px] mb-1">ធាតុម៉ឺនុយ</p>
                <p class="text-muted text-[12px] mb-4">អូសរូបតំណាង ⠿ ដើម្បីតម្រៀបលំដាប់ ឬផ្លាស់ទីធាតុទៅក្នុងម៉ឺនុយរង។</p>

                <div data-menu-sort-root data-reorder-url="{{ route('admin.menus.items.reorder', $menu) }}">
                    <ul class="menu-tree flex flex-col gap-2" data-parent-id="">
                        @forelse ($tree as $item)
                            @include('admin.menus._tree-item', ['item' => $item, 'menu' => $menu])
                        @empty
                            <li class="text-muted text-[13px] py-6 text-center list-none">មិនទាន់មានធាតុម៉ឺនុយទេ។</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-5 h-fit">
            <p class="font-bold text-brand-navy text-[15px] mb-1">មើលមុន (Preview)</p>
            <p class="text-muted text-[12px] mb-4">របៀបដែលម៉ឺនុយនេះនឹងបង្ហាញនៅលើគេហទំព័រ។ ធាតុពណ៌ប្រផេះមានន័យថាបានបិទ។</p>

            <div class="border border-line rounded-[8px] p-4 bg-surface">
                <ul class="flex flex-col gap-1">
                    @forelse ($tree as $item)
                        <li>
                            <div class="flex items-center gap-2 {{ $item->is_active ? 'text-ink' : 'text-gray-400' }} text-[13px] font-bold py-1">
                                @if ($item->icon)<span>{{ $item->icon }}</span>@endif
                                {{ $item->label_km }}
                                @if ($item->target === '_blank')<span class="text-[10px] text-muted">↗</span>@endif
                            </div>
                            @if ($item->children->isNotEmpty())
                                <ul class="flex flex-col gap-1 pl-5 border-l border-line ml-1">
                                    @foreach ($item->children as $child)
                                        <li class="flex items-center gap-2 {{ $child->is_active ? 'text-muted' : 'text-gray-400' }} text-[12px] py-1">
                                            @if ($child->icon)<span>{{ $child->icon }}</span>@endif
                                            {{ $child->label_km }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @empty
                        <li class="text-muted text-[13px] text-center py-4">មិនទាន់មានធាតុទេ។</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-admin.layout>
