<x-admin.layout title="ទំព័រសំខាន់ៗ">
    <div class="flex items-center justify-between mb-5">
        <p class="text-muted text-[13px]">គ្រប់គ្រងកាត "ទំព័រសំខាន់ៗ" (ផ្លូវកាត់សំខាន់ៗ) នៅលើទំព័រដើម រួមទាំងរូបភាពរបស់វា។</p>
        @can('create', \App\Models\HomepageQuickLink::class)
            <a href="{{ route('admin.homepage-quick-links.create') }}" class="bg-brand-green text-white font-bold text-[13px] rounded-[8px] h-10 px-5 flex items-center whitespace-nowrap">+ បង្កើតកាតថ្មី</a>
        @endcan
    </div>

    <x-admin.data-table :headers="['រូបភាព', 'ចំណងជើង', 'តំណភ្ជាប់', 'លំដាប់', 'ស្ថានភាព', '']">
        @forelse ($links as $link)
            <tr>
                <td class="px-4 py-3">
                    @if ($link->imageUrl())
                        <img src="{{ $link->imageUrl() }}" class="w-[64px] aspect-[16/7] object-cover rounded-[6px] border border-line">
                    @else
                        <div class="w-[64px] aspect-[16/7] rounded-[6px] border border-dashed border-line"></div>
                    @endif
                </td>
                <td class="px-4 py-3 font-bold text-ink text-[14px]">{{ $link->title_km }}</td>
                <td class="px-4 py-3 text-[12px] text-muted">
                    {{ $link->link_type === 'route' ? $link->route_name : $link->url }}
                </td>
                <td class="px-4 py-3 text-[13px] text-ink">{{ $link->sort_order }}</td>
                <td class="px-4 py-3">
                    <span class="{{ $link->is_active ? 'bg-brand-green-tint text-brand-green' : 'bg-gray-100 text-gray-500' }} inline-flex rounded-full px-3 py-1 text-[12px] font-bold">
                        {{ $link->is_active ? 'បើក' : 'បិទ' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-right">
                    <div class="flex justify-end gap-3">
                        @can('update', $link)
                            <form method="POST" action="{{ route('admin.homepage-quick-links.toggle', $link) }}">
                                @csrf
                                <button type="submit" class="{{ $link->is_active ? 'text-muted' : 'text-brand-green' }} text-[13px] font-bold">{{ $link->is_active ? 'បិទ' : 'បើក' }}</button>
                            </form>
                            <a href="{{ route('admin.homepage-quick-links.edit', $link) }}" class="text-brand-blue text-[13px] font-bold">កែប្រែ</a>
                        @endcan
                        @can('delete', $link)
                            <x-admin.confirmation-dialog :action="route('admin.homepage-quick-links.destroy', $link)" method="DELETE" message="លុបកាតនេះ?" label="លុប" class="bg-red-50 text-brand-red" />
                        @endcan
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-state message="មិនទាន់មានកាតទំព័រសំខាន់ទេ។" />
        @endforelse
    </x-admin.data-table>

    <div class="mt-4">{{ $links->links() }}</div>
</x-admin.layout>
