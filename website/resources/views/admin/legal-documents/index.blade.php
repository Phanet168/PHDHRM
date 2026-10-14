<x-admin.layout title="លិខិតបទដ្ឋានគតិយុត្ត">
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <form method="GET" class="flex flex-wrap gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="ស្វែងរកចំណងជើង..."
                   class="bg-white border border-line rounded-[8px] h-10 px-4 text-[13px] w-[220px]">
            <select name="category" class="bg-white border border-line rounded-[8px] h-10 px-3 text-[13px]">
                <option value="">គ្រប់ប្រភេទ</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) request('category') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="year" class="bg-white border border-line rounded-[8px] h-10 px-3 text-[13px]">
                <option value="">គ្រប់ឆ្នាំ</option>
                @foreach ($years as $year)
                    <option value="{{ $year }}" @selected((string) request('year') === (string) $year)>{{ $year }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-10 px-5">ស្វែងរក</button>
        </form>

        @can('create', \App\Models\LegalDocument::class)
            <a href="{{ route('admin.legal-documents.create') }}" class="bg-brand-green text-white font-bold text-[13px] rounded-[8px] h-10 px-5 flex items-center">+ បញ្ចូលឯកសារ</a>
        @endcan
    </div>

    <x-admin.data-table :headers="['ចំណងជើង', 'ប្រភេទ', 'លេខ', 'ឆ្នាំ', 'ស្ថានភាព', '']">
        @forelse ($documents as $document)
            <tr>
                <td class="px-4 py-3 font-bold text-ink text-[14px] max-w-[280px] truncate">{{ $document->title }}</td>
                <td class="px-4 py-3 text-[13px] text-ink">{{ $document->category?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-[13px] text-ink">{{ $document->document_number ?? '—' }}</td>
                <td class="px-4 py-3 text-[13px] text-ink">{{ $document->year ?? '—' }}</td>
                <td class="px-4 py-3"><x-admin.status-badge :status="$document->status" /></td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.legal-documents.edit', $document) }}" class="text-brand-blue text-[13px] font-bold">កែប្រែ →</a>
                </td>
            </tr>
        @empty
            <x-admin.empty-state message="មិនមានឯកសារទេ។" />
        @endforelse
    </x-admin.data-table>

    <div class="mt-4">{{ $documents->links() }}</div>
</x-admin.layout>
