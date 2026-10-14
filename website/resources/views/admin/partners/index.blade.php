<x-admin.layout title="អង្គការដៃគូ">
    <div class="flex items-center justify-end mb-5">
        <a href="{{ route('admin.partners.create') }}" class="bg-brand-green text-white font-bold text-[13px] rounded-[8px] h-10 px-5 flex items-center">+ បន្ថែមអង្គការដៃគូ</a>
    </div>

    <x-admin.data-table :headers="['ឈ្មោះ', 'គេហទំព័រ', 'ស្ថានភាព', '']">
        @forelse ($partners as $partner)
            <tr>
                <td class="px-4 py-3 font-bold text-ink text-[14px]">{{ $partner->name }}</td>
                <td class="px-4 py-3 text-[13px] text-brand-blue">{{ $partner->website ?? '—' }}</td>
                <td class="px-4 py-3">
                    <span class="{{ $partner->active ? 'bg-brand-green-tint text-brand-green' : 'bg-gray-100 text-gray-500' }} inline-flex rounded-full px-3 py-1 text-[12px] font-bold">
                        {{ $partner->active ? 'សកម្ម' : 'អសកម្ម' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-right flex justify-end gap-3">
                    <a href="{{ route('admin.partners.edit', $partner) }}" class="text-brand-blue text-[13px] font-bold">កែប្រែ</a>
                    <x-admin.confirmation-dialog :action="route('admin.partners.destroy', $partner)" method="DELETE" message="លុបអង្គការដៃគូនេះ?" label="លុប" class="bg-red-50 text-brand-red" />
                </td>
            </tr>
        @empty
            <x-admin.empty-state message="មិនមានអង្គការដៃគូទេ។" />
        @endforelse
    </x-admin.data-table>

    <div class="mt-4">{{ $partners->links() }}</div>
</x-admin.layout>
