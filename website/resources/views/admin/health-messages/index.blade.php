<x-admin.layout title="សារអប់រំសុខភាព">
    <div class="flex items-center justify-end mb-5">
        <a href="{{ route('admin.health-messages.create') }}" class="bg-brand-green text-white font-bold text-[13px] rounded-[8px] h-10 px-5 flex items-center">+ បន្ថែមសារ</a>
    </div>

    <x-admin.data-table :headers="['លំដាប់', 'សារ', 'រូបតំណាង', 'ប្រភព', 'ស្ថានភាព', '']">
        @forelse ($messages as $message)
            <tr>
                <td class="px-4 py-3 text-[13px] text-muted">{{ $message->sort_order }}</td>
                <td class="px-4 py-3 font-bold text-ink text-[14px] max-w-[320px] truncate">{{ $message->message_km }}</td>
                <td class="px-4 py-3 text-[13px] text-ink">{{ $message->icon }}</td>
                <td class="px-4 py-3 text-[13px] text-muted">{{ $message->source_name ?? '—' }}</td>
                <td class="px-4 py-3">
                    <span class="{{ $message->is_active ? 'bg-brand-green-tint text-brand-green' : 'bg-gray-100 text-gray-500' }} inline-flex rounded-full px-3 py-1 text-[12px] font-bold">
                        {{ $message->is_active ? 'សកម្ម' : 'អសកម្ម' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-right flex justify-end gap-3">
                    <a href="{{ route('admin.health-messages.edit', $message) }}" class="text-brand-blue text-[13px] font-bold">កែប្រែ</a>
                    <x-admin.confirmation-dialog :action="route('admin.health-messages.destroy', $message)" method="DELETE" message="លុបសារនេះ?" label="លុប" class="bg-red-50 text-brand-red" />
                </td>
            </tr>
        @empty
            <x-admin.empty-state message="មិនមានសារអប់រំសុខភាពទេ។" />
        @endforelse
    </x-admin.data-table>

    <div class="mt-4">{{ $messages->links() }}</div>
</x-admin.layout>
