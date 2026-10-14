<x-admin.layout title="អនុសាសន៍ថ្នាក់ដឹកនាំ">
    <div class="flex items-center justify-between mb-5">
        <p class="text-muted text-[13px]">គ្រប់គ្រងផ្នែក "អនុសាសន៍ថ្នាក់ដឹកនាំ" នៅលើទំព័រដើម។ ទំព័រដើមបង្ហាញសំណុំចុងក្រោយដែលបានបើក និងផ្សាយ។</p>
        @can('create', \App\Models\LeaderRecommendation::class)
            <a href="{{ route('admin.leader-recommendations.create') }}" class="bg-brand-green text-white font-bold text-[13px] rounded-[8px] h-10 px-5 flex items-center whitespace-nowrap">+ បង្កើតសំណុំថ្មី</a>
        @endcan
    </div>

    <x-admin.data-table :headers="['ចំណងជើង', 'ថ្នាក់ដឹកនាំ', 'ចំនួនអនុសាសន៍', 'កាលបរិច្ឆេទផ្សព្វផ្សាយ', 'ស្ថានភាព', '']">
        @forelse ($recommendations as $recommendation)
            <tr>
                <td class="px-4 py-3 font-bold text-ink text-[14px]">{{ $recommendation->title_km }}</td>
                <td class="px-4 py-3 text-[13px] text-ink">
                    {{ $recommendation->leader_name_km }}
                    @if ($recommendation->leader_position_km)
                        <span class="text-muted text-[12px]">— {{ $recommendation->leader_position_km }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-[13px] text-ink">{{ $recommendation->items_count }}</td>
                <td class="px-4 py-3 text-[12px] text-muted">{{ $recommendation->published_at?->format('d-m-Y') ?? '—' }}</td>
                <td class="px-4 py-3">
                    <span class="{{ $recommendation->is_active ? 'bg-brand-green-tint text-brand-green' : 'bg-gray-100 text-gray-500' }} inline-flex rounded-full px-3 py-1 text-[12px] font-bold">
                        {{ $recommendation->is_active ? 'បើក' : 'បិទ' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-right">
                    <div class="flex justify-end gap-3">
                        @can('update', $recommendation)
                            <form method="POST" action="{{ route('admin.leader-recommendations.toggle', $recommendation) }}">
                                @csrf
                                <button type="submit" class="{{ $recommendation->is_active ? 'text-muted' : 'text-brand-green' }} text-[13px] font-bold">{{ $recommendation->is_active ? 'បិទ' : 'បើក' }}</button>
                            </form>
                            <a href="{{ route('admin.leader-recommendations.edit', $recommendation) }}" class="text-brand-blue text-[13px] font-bold">កែប្រែ</a>
                        @endcan
                        @can('delete', $recommendation)
                            <x-admin.confirmation-dialog :action="route('admin.leader-recommendations.destroy', $recommendation)" method="DELETE" message="លុបសំណុំនេះ?" label="លុប" class="bg-red-50 text-brand-red" />
                        @endcan
                    </div>
                </td>
            </tr>
        @empty
            <x-admin.empty-state message="មិនទាន់មានអនុសាសន៍ថ្នាក់ដឹកនាំទេ។" />
        @endforelse
    </x-admin.data-table>

    <div class="mt-4">{{ $recommendations->links() }}</div>
</x-admin.layout>
