<x-admin.layout title="អំពីមន្ទីរ · ថ្នាក់ដឹកនាំ">
    @include('admin.about._nav')
    @include('admin.about._toolbar', ['heading' => 'ថ្នាក់ដឹកនាំមន្ទីរ', 'hint' => 'ថ្នាក់ដឹកនាំថ្នាក់មន្ទីរ (មិនមែនថ្នាក់ដឹកនាំមូលដ្ឋានសុខាភិបាល)។ អូស ⠿ ដើម្បីតម្រៀប។', 'indexUrl' => route('admin.about.leaders.index'), 'createUrl' => route('admin.about.leaders.create')])

    <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] overflow-x-auto" @unless ($trashed) data-block-sort-root data-reorder-url="{{ route('admin.about.leaders.reorder') }}" @endunless>
        <table class="min-w-full">
            <thead>
                <tr class="bg-surface border-b border-line text-[12px] text-muted text-left">
                    <th class="px-3 py-3 w-8"><span class="sr-only">តម្រៀប</span></th>
                    <th class="font-bold px-4 py-3">រូបថត</th>
                    <th class="font-bold px-4 py-3">ឈ្មោះ</th>
                    <th class="font-bold px-4 py-3">តួនាទី</th>
                    <th class="font-bold px-4 py-3">លំដាប់</th>
                    <th class="font-bold px-4 py-3">ស្ថានភាព</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="content-block-list divide-y divide-line">
                @forelse ($leaders as $leader)
                    @php $portrait = $leader->portraitUrl(); @endphp
                    <tr data-id="{{ $leader->id }}">
                        <td class="px-3 py-3 text-muted">@unless ($trashed)<span class="block-drag-handle cursor-grab select-none" title="អូសដើម្បីតម្រៀប" aria-hidden="true">⠿</span>@endunless</td>
                        <td class="px-4 py-3">
                            @if ($portrait)
                                <img src="{{ $portrait }}" alt="" class="size-[52px] rounded-[8px] object-cover object-top border border-line">
                            @else
                                <div class="size-[52px] rounded-[8px] border border-dashed border-line flex items-center justify-center text-muted"><x-news-icon name="user" /></div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-bold text-ink text-[14px]">{{ $leader->name }} @if ($leader->is_featured)<span class="text-amber-600" title="លេចធ្លោ">★</span>@endif</p>
                            @if ($leader->name_en)<p class="text-muted text-[12px]">{{ $leader->name_en }}</p>@endif
                        </td>
                        <td class="px-4 py-3 text-[13px] text-ink">{{ $leader->title }}</td>
                        <td class="px-4 py-3 text-[13px] text-ink">{{ $leader->sort_order }}</td>
                        <td class="px-4 py-3">@include('admin.about._status', ['active' => $leader->is_active, 'demo' => $leader->is_demo])</td>
                        <td class="px-4 py-3">
                            @include('admin.about._actions', [
                                'active' => $leader->is_active, 'name' => $leader->name,
                                'toggleUrl' => route('admin.about.leaders.toggle', $leader),
                                'editUrl' => route('admin.about.leaders.edit', $leader),
                                'destroyUrl' => route('admin.about.leaders.destroy', $leader),
                                'restoreUrl' => route('admin.about.leaders.restore', $leader->id),
                                'forceUrl' => route('admin.about.leaders.force-destroy', $leader->id),
                            ])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center py-12">
                            <p class="text-muted text-[14px] mb-3">{{ $trashed ? 'ធុងសំរាមទទេ' : 'មិនទាន់មានទិន្នន័យ' }}</p>
                            @unless ($trashed)<a href="{{ route('admin.about.leaders.create') }}" class="bg-brand-green text-white font-bold text-[13px] rounded-[8px] h-10 px-5 inline-flex items-center">+ បន្ថែម</a>@endunless
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.layout>
