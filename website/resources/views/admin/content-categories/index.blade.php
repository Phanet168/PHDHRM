<x-admin.layout title="ប្រភេទមាតិកា">
    <div class="flex items-center justify-between mb-5">
        <p class="text-muted text-[13px]">គ្រប់គ្រងប្រភេទមាតិកា (អាចដាក់ជាប្រភេទរង តាមរយៈប្រភេទមេ)។</p>
        @can('create', \App\Models\ContentCategory::class)
            <a href="{{ route('admin.content-categories.create') }}" class="bg-brand-green text-white font-bold text-[13px] rounded-[8px] h-10 px-5 flex items-center">+ បង្កើតប្រភេទ</a>
        @endcan
    </div>

    <x-admin.data-table :headers="['ឈ្មោះប្រភេទ', 'ប្រភេទមេ', 'ចំនួនអត្ថបទ', 'ស្ថានភាព', '']">
        @forelse ($categories as $category)
            <tr>
                <td class="px-4 py-3 font-bold text-ink text-[14px]">{{ $category->name_km }}</td>
                <td class="px-4 py-3 text-[13px] text-muted">{{ $category->parent?->name_km ?? '—' }}</td>
                <td class="px-4 py-3 text-[13px] text-ink">{{ $category->contents_count }}</td>
                <td class="px-4 py-3">
                    <span class="{{ $category->is_active ? 'bg-brand-green-tint text-brand-green' : 'bg-gray-100 text-gray-500' }} inline-flex rounded-full px-3 py-1 text-[12px] font-bold">
                        {{ $category->is_active ? 'សកម្ម' : 'អសកម្ម' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-right flex justify-end gap-3">
                    <a href="{{ route('admin.content-categories.edit', $category) }}" class="text-brand-blue text-[13px] font-bold">កែប្រែ</a>
                    @can('delete', $category)
                        <form method="POST" action="{{ route('admin.content-categories.destroy', $category) }}" onsubmit="return confirm('លុបប្រភេទនេះ?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-brand-red text-[13px] font-bold">លុប</button>
                        </form>
                    @endcan
                </td>
            </tr>
        @empty
            <x-admin.empty-state message="មិនមានប្រភេទមាតិកាទេ។" />
        @endforelse
    </x-admin.data-table>
</x-admin.layout>
