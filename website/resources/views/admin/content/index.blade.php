<x-admin.layout title="អត្ថបទ">
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <form method="GET" class="flex flex-wrap gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="ស្វែងរកចំណងជើង..."
                   class="bg-white border border-line rounded-[8px] h-10 px-4 text-[13px] w-[220px]">
            <select name="category" class="bg-white border border-line rounded-[8px] h-10 px-3 text-[13px]">
                <option value="">គ្រប់ប្រភេទ</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) request('category') === (string) $category->id)>{{ $category->name_km }}</option>
                @endforeach
            </select>
            <select name="status" class="bg-white border border-line rounded-[8px] h-10 px-3 text-[13px]">
                <option value="">គ្រប់ស្ថានភាព</option>
                @foreach (['draft' => 'សេចក្តីព្រាង', 'pending_review' => 'រង់ចាំត្រួតពិនិត្យ', 'approved' => 'បានអនុម័ត', 'published' => 'បានផ្សាយ', 'archived' => 'ឯកសារ'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-10 px-5">ស្វែងរក</button>
        </form>

        @can('create', \App\Models\Content::class)
            <a href="{{ route('admin.content.create') }}" class="bg-brand-green text-white font-bold text-[13px] rounded-[8px] h-10 px-5 flex items-center">+ បង្កើតអត្ថបទ</a>
        @endcan
    </div>

    <x-admin.data-table :headers="['ចំណងជើង', 'ប្រភេទ', 'អ្នកនិពន្ធ', 'ស្ថានភាព', 'កាលបរិច្ឆេទ', '']">
        @forelse ($contents as $item)
            <tr>
                <td class="px-4 py-3 font-bold text-ink text-[14px] max-w-[280px] truncate">
                    {{ $item->title_km }}
                    @if ($item->is_featured)<span class="text-amber-500" title="លេចធ្លោ">★</span>@endif
                </td>
                <td class="px-4 py-3 text-[13px] text-ink">{{ $item->category?->name_km ?? '—' }}</td>
                <td class="px-4 py-3 text-[13px] text-ink">{{ $item->author?->name ?? '—' }}</td>
                <td class="px-4 py-3"><x-admin.status-badge :status="$item->status" /></td>
                <td class="px-4 py-3 text-[12px] text-muted">{{ $item->updated_at->format('d-m-Y') }}</td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.content.edit', $item) }}" class="text-brand-blue text-[13px] font-bold">កែប្រែ →</a>
                </td>
            </tr>
        @empty
            <x-admin.empty-state message="មិនមានអត្ថបទទេ។" />
        @endforelse
    </x-admin.data-table>

    <div class="mt-4">{{ $contents->links() }}</div>
</x-admin.layout>
