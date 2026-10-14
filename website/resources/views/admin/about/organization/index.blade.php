<x-admin.layout title="អំពីមន្ទីរ · រចនាសម្ព័ន្ធអង្គភាព">
    @include('admin.about._nav')
    @include('admin.about._toolbar', [
        'heading' => 'រចនាសម្ព័ន្ធអង្គភាព',
        'hint' => 'អង្គភាពត្រូវបានដាក់ជាក្រុមតាមប្រភេទនៅលើទំព័រសាធារណៈ។ មន្ទីរពេទ្យ/ស្រុកប្រតិបត្តិ អាចភ្ជាប់ទៅមូលដ្ឋានសុខាភិបាលដែលមានស្រាប់។ អូស ⠿ ដើម្បីតម្រៀប។',
        'indexUrl' => route('admin.about.organization.index'),
        'createUrl' => route('admin.about.organization.create'),
        'extra' => $importable > 0 ? new \Illuminate\Support\HtmlString(
            '<form method="POST" action="'.e(route('admin.about.organization.import')).'">'.csrf_field()
            .'<button type="submit" class="border border-brand-blue text-brand-blue font-bold text-[13px] rounded-[8px] h-10 px-4">នាំចូលពីមូលដ្ឋានសុខាភិបាល ('.$importable.')</button></form>'
        ) : null,
    ])

    <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] overflow-x-auto" @unless ($trashed) data-block-sort-root data-reorder-url="{{ route('admin.about.organization.reorder') }}" @endunless>
        <table class="min-w-full">
            <thead>
                <tr class="bg-surface border-b border-line text-[12px] text-muted text-left">
                    <th class="px-3 py-3 w-8"><span class="sr-only">តម្រៀប</span></th>
                    <th class="font-bold px-4 py-3">ឈ្មោះ</th>
                    <th class="font-bold px-4 py-3">ប្រភេទ</th>
                    <th class="font-bold px-4 py-3">ស្ថិតក្រោម</th>
                    <th class="font-bold px-4 py-3">មូលដ្ឋានសុខាភិបាល</th>
                    <th class="font-bold px-4 py-3">លំដាប់</th>
                    <th class="font-bold px-4 py-3">ស្ថានភាព</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="content-block-list divide-y divide-line">
                @forelse ($units as $unit)
                    <tr data-id="{{ $unit->id }}">
                        <td class="px-3 py-3 text-muted">@unless ($trashed)<span class="block-drag-handle cursor-grab select-none" title="អូសដើម្បីតម្រៀប" aria-hidden="true">⠿</span>@endunless</td>
                        <td class="px-4 py-3 font-bold text-ink text-[14px]">@if ($unit->parent_id)<span class="text-muted">↳ </span>@endif{{ $unit->name }}</td>
                        <td class="px-4 py-3 text-[13px] text-ink whitespace-nowrap">{{ \App\Models\AboutOrgUnit::typeLabel($unit->type) }}</td>
                        <td class="px-4 py-3 text-[12px] text-muted">{{ $unit->parent?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-[12px] text-muted">
                            @if ($unit->facility){{ $unit->facility->name_km }} @if ($unit->facility->status !== 'published')<span class="text-amber-700">(មិនទាន់ផ្សាយ — គ្មានតំណ)</span>@endif @else — @endif
                        </td>
                        <td class="px-4 py-3 text-[13px] text-ink">{{ $unit->sort_order }}</td>
                        <td class="px-4 py-3">@include('admin.about._status', ['active' => $unit->is_active, 'demo' => $unit->is_demo])</td>
                        <td class="px-4 py-3">
                            @include('admin.about._actions', [
                                'active' => $unit->is_active, 'name' => $unit->name,
                                'toggleUrl' => route('admin.about.organization.toggle', $unit),
                                'editUrl' => route('admin.about.organization.edit', $unit),
                                'destroyUrl' => route('admin.about.organization.destroy', $unit),
                                'restoreUrl' => route('admin.about.organization.restore', $unit->id),
                                'forceUrl' => route('admin.about.organization.force-destroy', $unit->id),
                            ])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center py-12">
                            <p class="text-muted text-[14px] mb-1">{{ $trashed ? 'ធុងសំរាមទទេ' : 'មិនទាន់មានទិន្នន័យ' }}</p>
                            @unless ($trashed)
                                <p class="text-muted text-[12px] mb-3">ខណៈពេលនៅទទេ ទំព័រសាធារណៈបង្ហាញមន្ទីរពេទ្យបង្អែក និងការិយាល័យស្រុកប្រតិបត្តិដែលបានផ្សាយ ដោយស្វ័យប្រវត្តិ។</p>
                                <a href="{{ route('admin.about.organization.create') }}" class="bg-brand-green text-white font-bold text-[13px] rounded-[8px] h-10 px-5 inline-flex items-center">+ បន្ថែម</a>
                            @endunless
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.layout>
