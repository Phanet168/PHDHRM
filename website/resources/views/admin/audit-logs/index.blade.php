<x-admin.layout title="Audit Logs">
    <form method="GET" class="flex flex-wrap gap-2 mb-5">
        <select name="action" class="bg-white border border-line rounded-[8px] h-10 px-3 text-[13px]" onchange="this.form.submit()">
            <option value="">គ្រប់សកម្មភាព</option>
            @foreach (['created', 'updated', 'deleted', 'submitted_for_review', 'approved', 'published', 'unpublished', 'login', 'logout'] as $action)
                <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
            @endforeach
        </select>
        <select name="entity_type" class="bg-white border border-line rounded-[8px] h-10 px-3 text-[13px]" onchange="this.form.submit()">
            <option value="">គ្រប់ប្រភេទ</option>
            @foreach ($entityTypes as $type)
                <option value="{{ $type }}" @selected(request('entity_type') === $type)>{{ $type }}</option>
            @endforeach
        </select>
    </form>

    <x-admin.data-table :headers="['ព័ត៌មាន', 'អ្នកប្រើប្រាស់', 'សកម្មភាព', 'ប្រភេទ', 'ពេលវេលា']">
        @forelse ($logs as $log)
            <tr>
                <td class="px-4 py-3 text-[13px] text-ink max-w-[320px] truncate">{{ $log->description }}</td>
                <td class="px-4 py-3 text-[13px] text-ink">{{ $log->user?->name ?? 'System' }}</td>
                <td class="px-4 py-3 text-[12px] text-brand-blue font-bold">{{ $log->action }}</td>
                <td class="px-4 py-3 text-[12px] text-muted">{{ $log->entity_type }}</td>
                <td class="px-4 py-3 text-[12px] text-muted whitespace-nowrap">{{ $log->created_at->format('d-m-Y H:i') }}</td>
            </tr>
        @empty
            <x-admin.empty-state message="មិនមានកំណត់ត្រាទេ។" />
        @endforelse
    </x-admin.data-table>

    <div class="mt-4">{{ $logs->links() }}</div>
</x-admin.layout>
