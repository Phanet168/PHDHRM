@php
    $headings = ['cards' => 'កាតព័ត៌មានស្នូល', 'timeline' => 'ប្រវត្តិ និងការអភិវឌ្ឍ (Timeline)', 'documents' => 'ឯកសារពាក់ព័ន្ធ'];
    $hints = [
        'cards' => 'កាតចំនួន ៤ (ចក្ខុវិស័យ បេសកកម្ម តួនាទី គោលតម្លៃ) បង្ហាញក្រោមសារបេសកកម្ម។ អូស ⠿ ដើម្បីតម្រៀប។',
        'timeline' => 'ព្រឹត្តិការណ៍ប្រវត្តិតាមលំដាប់។ អូស ⠿ ដើម្បីតម្រៀប។ សូមបញ្ចូលតែព័ត៌មានផ្លូវការដែលបានផ្ទៀងផ្ទាត់។',
        'documents' => 'ឯកសារពី Media Library ឬលិខិតបទដ្ឋានដែលបានផ្សាយ — បង្ហាញក្នុងរបារចំហៀង។ អូស ⠿ ដើម្បីតម្រៀប។',
    ];
@endphp
<x-admin.layout :title="'អំពីមន្ទីរ · '.$headings[$group]">
    @include('admin.about._nav')
    @include('admin.about._toolbar', ['heading' => $headings[$group], 'hint' => $hints[$group], 'indexUrl' => route('admin.about.items.index', $group), 'createUrl' => route('admin.about.items.create', $group)])

    <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] overflow-x-auto" @unless ($trashed) data-block-sort-root data-reorder-url="{{ route('admin.about.items.reorder', $group) }}" @endunless>
        <table class="min-w-full">
            <thead>
                <tr class="bg-surface border-b border-line text-[12px] text-muted text-left">
                    <th class="px-3 py-3 w-8"><span class="sr-only">តម្រៀប</span></th>
                    @if ($group === 'timeline')<th class="font-bold px-4 py-3">ឆ្នាំ / កាលបរិច្ឆេទ</th>@endif
                    <th class="font-bold px-4 py-3">ចំណងជើង</th>
                    <th class="font-bold px-4 py-3">{{ $group === 'documents' ? 'ប្រភពឯកសារ' : 'ការពិពណ៌នា' }}</th>
                    <th class="font-bold px-4 py-3">លំដាប់</th>
                    <th class="font-bold px-4 py-3">ស្ថានភាព</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="content-block-list divide-y divide-line">
                @forelse ($items as $item)
                    <tr data-id="{{ $item->id }}" class="align-top">
                        <td class="px-3 py-3 text-muted">@unless ($trashed)<span class="block-drag-handle cursor-grab select-none" title="អូសដើម្បីតម្រៀប" aria-hidden="true">⠿</span>@endunless</td>
                        @if ($group === 'timeline')<td class="px-4 py-3 font-bold text-brand-blue text-[14px] whitespace-nowrap">{{ $item->date_label }}</td>@endif
                        <td class="px-4 py-3">
                            <p class="font-bold text-ink text-[14px] flex items-center gap-2">@if ($item->icon_key)<x-news-icon :name="\App\Support\AboutIcon::key($item->icon_key)" width="16" height="16" />@endif{{ $item->title }}</p>
                            @if ($group === 'cards' && $item->link_url)<p class="text-muted text-[11px] mt-1">↗ {{ $item->link_url }}</p>@endif
                        </td>
                        <td class="px-4 py-3 text-[12px] text-muted max-w-[420px]">
                            @if ($group === 'documents')
                                @if ($item->media)
                                    Media · {{ $item->media->original_filename }} · {{ $item->media->formattedSize() }}
                                @elseif ($item->legalDocument)
                                    លិខិតបទដ្ឋាន · {{ $item->legalDocument->title }} @if ($item->legalDocument->status !== 'published')<span class="text-brand-red font-bold">(មិនបានផ្សាយ — មិនបង្ហាញ)</span>@endif
                                @else
                                    <span class="text-brand-red font-bold">ប្រភពត្រូវបានលុប — មិនបង្ហាញ</span>
                                @endif
                            @else
                                {{ \Illuminate\Support\Str::limit($item->body, 140) }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-[13px] text-ink">{{ $item->sort_order }}</td>
                        <td class="px-4 py-3">@include('admin.about._status', ['active' => $item->is_active, 'demo' => $item->is_demo])</td>
                        <td class="px-4 py-3">
                            @include('admin.about._actions', [
                                'active' => $item->is_active, 'name' => $item->title,
                                'toggleUrl' => route('admin.about.items.toggle', [$group, $item]),
                                'editUrl' => route('admin.about.items.edit', [$group, $item]),
                                'destroyUrl' => route('admin.about.items.destroy', [$group, $item]),
                                'restoreUrl' => route('admin.about.items.restore', [$group, $item->id]),
                                'forceUrl' => route('admin.about.items.force-destroy', [$group, $item->id]),
                            ])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center py-12">
                            <p class="text-muted text-[14px] mb-3">{{ $trashed ? 'ធុងសំរាមទទេ' : 'មិនទាន់មានទិន្នន័យ' }}</p>
                            @unless ($trashed)<a href="{{ route('admin.about.items.create', $group) }}" class="bg-brand-green text-white font-bold text-[13px] rounded-[8px] h-10 px-5 inline-flex items-center">+ បន្ថែម</a>@endunless
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.layout>
