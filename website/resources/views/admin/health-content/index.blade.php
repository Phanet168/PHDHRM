<x-admin.layout title="មាតិកាអប់រំសុខភាព">
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

        <div class="flex gap-2">
            @can('import', \App\Models\HealthContent::class)
                <a href="{{ route('admin.health-content.youtube-import.create') }}" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-10 px-5 flex items-center">នាំចូលពី YouTube</a>
            @endcan
            @can('create', \App\Models\HealthContent::class)
                <a href="{{ route('admin.health-content.create') }}" class="bg-brand-green text-white font-bold text-[13px] rounded-[8px] h-10 px-5 flex items-center">+ បង្កើតមាតិកា</a>
            @endcan
        </div>
    </div>

    <x-admin.data-table :headers="['ចំណងជើង', 'ប្រភេទ', 'ស្ថានភាព', 'កាលបរិច្ឆេទ', '']">
        @forelse ($contents as $content)
            <tr>
                <td class="px-4 py-3 font-bold text-ink text-[14px] max-w-[280px] truncate">{{ $content->title_km }}</td>
                <td class="px-4 py-3 text-[13px] text-ink">{{ $content->category?->name_km ?? '—' }}</td>
                <td class="px-4 py-3"><x-admin.status-badge :status="$content->status" /></td>
                <td class="px-4 py-3 text-[12px] text-muted">{{ $content->updated_at->format('d-m-Y') }}</td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.health-content.edit', $content) }}" class="text-brand-blue text-[13px] font-bold">កែប្រែ →</a>
                </td>
            </tr>
        @empty
            <x-admin.empty-state message="មិនមានមាតិកាទេ។" />
        @endforelse
    </x-admin.data-table>

    <div class="mt-4">{{ $contents->links() }}</div>
</x-admin.layout>
