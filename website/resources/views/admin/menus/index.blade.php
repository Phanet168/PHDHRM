<x-admin.layout title="ម៉ឺនុយគេហទំព័រ">
    <div class="flex items-center justify-between mb-5">
        <p class="text-muted text-[13px]">គ្រប់គ្រងក្រុមម៉ឺនុយសម្រាប់ផ្នែកផ្សេងៗនៃគេហទំព័រ (Header, Footer, Mobile)។</p>
        @can('create', \App\Models\Menu::class)
            <a href="{{ route('admin.menus.create') }}" class="bg-brand-green text-white font-bold text-[13px] rounded-[8px] h-10 px-5 flex items-center whitespace-nowrap">+ បង្កើតម៉ឺនុយ</a>
        @endcan
    </div>

    <x-admin.data-table :headers="['ឈ្មោះ', 'Slug', 'ចំនួនធាតុ', 'ស្ថានភាព', '']">
        @forelse ($menus as $menu)
            <tr>
                <td class="px-4 py-3 font-bold text-ink text-[14px]">{{ $menu->name }}</td>
                <td class="px-4 py-3 text-[13px] text-muted">{{ $menu->slug }}</td>
                <td class="px-4 py-3 text-[13px] text-ink">{{ $menu->items_count }}</td>
                <td class="px-4 py-3">
                    <span class="{{ $menu->is_active ? 'bg-brand-green-tint text-brand-green' : 'bg-gray-100 text-gray-500' }} inline-flex rounded-full px-3 py-1 text-[12px] font-bold">
                        {{ $menu->is_active ? 'បានផ្សាយ' : 'សេចក្តីព្រាង' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-right flex justify-end gap-3">
                    <a href="{{ route('admin.menus.edit', $menu) }}" class="text-brand-blue text-[13px] font-bold">គ្រប់គ្រងធាតុ →</a>
                    <x-admin.confirmation-dialog :action="route('admin.menus.destroy', $menu)" method="DELETE" message="លុបម៉ឺនុយនេះ ព្រមទាំងធាតុទាំងអស់?" label="លុប" class="bg-red-50 text-brand-red" />
                </td>
            </tr>
        @empty
            <x-admin.empty-state message="មិនទាន់មានក្រុមម៉ឺនុយទេ។" />
        @endforelse
    </x-admin.data-table>
</x-admin.layout>
