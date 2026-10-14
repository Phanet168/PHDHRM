<x-admin.layout title="Hero Slider">
    <div class="flex items-center justify-between mb-5">
        <p class="text-muted text-[13px]">គ្រប់គ្រងស្លាយនៅលើផ្ទាំងខាងលើនៃទំព័រដើម។ ស្លាយត្រូវបានតម្រៀបតាមលេខលំដាប់។</p>
        @can('create', \App\Models\HeroSlide::class)
            <a href="{{ route('admin.hero-slides.create') }}" class="bg-brand-green text-white font-bold text-[13px] rounded-[8px] h-10 px-5 flex items-center whitespace-nowrap">+ បន្ថែម Slide</a>
        @endcan
    </div>

    <x-admin.data-table :headers="['រូបភាព', 'ចំណងជើង', 'លំដាប់', 'កាលបរិច្ឆេទផ្សព្វផ្សាយ', 'ស្ថានភាព', '']">
        @forelse ($slides as $slide)
            <tr>
                <td class="px-4 py-3">
                    <img src="{{ asset('storage/' . $slide->image) }}" alt="{{ $slide->title_km }}" class="h-[46px] w-[80px] object-cover rounded-[6px] border border-line">
                </td>
                <td class="px-4 py-3">
                    <p class="font-bold text-ink text-[14px]">{{ $slide->title_km }}</p>
                    @if ($slide->title_en)<p class="text-muted text-[12px]">{{ $slide->title_en }}</p>@endif
                </td>
                <td class="px-4 py-3 text-[13px] text-ink">{{ $slide->sort_order }}</td>
                <td class="px-4 py-3 text-[12px] text-muted">{{ $slide->published_at?->format('d-m-Y') ?? '—' }}</td>
                <td class="px-4 py-3">
                    <span class="{{ $slide->is_active ? 'bg-brand-green-tint text-brand-green' : 'bg-gray-100 text-gray-500' }} inline-flex rounded-full px-3 py-1 text-[12px] font-bold">
                        {{ $slide->is_active ? 'បើក' : 'បិទ' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-right flex justify-end gap-3">
                    <a href="{{ route('admin.hero-slides.edit', $slide) }}" class="text-brand-blue text-[13px] font-bold">កែប្រែ</a>
                    <x-admin.confirmation-dialog :action="route('admin.hero-slides.destroy', $slide)" method="DELETE" message="លុប Slide នេះ?" label="លុប" class="bg-red-50 text-brand-red" />
                </td>
            </tr>
        @empty
            <x-admin.empty-state message="មិនទាន់មាន Hero Slide ទេ។" />
        @endforelse
    </x-admin.data-table>

    <div class="mt-4">{{ $slides->links() }}</div>
</x-admin.layout>
