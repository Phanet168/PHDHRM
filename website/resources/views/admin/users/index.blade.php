<x-admin.layout title="អ្នកប្រើប្រាស់">
    <div class="flex items-center justify-end mb-5">
        <a href="{{ route('admin.users.create') }}" class="bg-brand-green text-white font-bold text-[13px] rounded-[8px] h-10 px-5 flex items-center">+ បន្ថែមអ្នកប្រើប្រាស់</a>
    </div>

    <x-admin.data-table :headers="['ឈ្មោះ', 'អ៊ីមែល', 'តួនាទី', 'មូលដ្ឋាន', 'ស្ថានភាព', '']">
        @forelse ($users as $user)
            <tr>
                <td class="px-4 py-3 font-bold text-ink text-[14px]">{{ $user->name }}</td>
                <td class="px-4 py-3 text-[13px] text-ink">{{ $user->email }}</td>
                <td class="px-4 py-3 text-[13px] text-ink">{{ $user->roles->pluck('name')->join(', ') ?: '—' }}</td>
                <td class="px-4 py-3 text-[13px] text-ink">{{ $user->facility?->name_km ?? '—' }}</td>
                <td class="px-4 py-3">
                    <span class="{{ $user->is_active ? 'bg-brand-green-tint text-brand-green' : 'bg-gray-100 text-gray-500' }} inline-flex rounded-full px-3 py-1 text-[12px] font-bold">
                        {{ $user->is_active ? 'សកម្ម' : 'បានផ្អាក' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-right flex justify-end gap-3">
                    <a href="{{ route('admin.users.edit', $user) }}" class="text-brand-blue text-[13px] font-bold">កែប្រែ</a>
                    @if ($user->id !== auth()->id())
                        <x-admin.confirmation-dialog :action="route('admin.users.destroy', $user)" method="DELETE" message="លុបអ្នកប្រើប្រាស់នេះ?" label="លុប" class="bg-red-50 text-brand-red" />
                    @endif
                </td>
            </tr>
        @empty
            <x-admin.empty-state message="មិនមានអ្នកប្រើប្រាស់ទេ។" />
        @endforelse
    </x-admin.data-table>

    <div class="mt-4">{{ $users->links() }}</div>
</x-admin.layout>
