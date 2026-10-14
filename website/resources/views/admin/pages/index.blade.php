@php
    $statusTabs = [
        '' => 'ទាំងអស់', 'draft' => 'សេចក្តីព្រាង', 'pending_review' => 'កំពុងរង់ចាំត្រួតពិនិត្យ',
        'approved' => 'បានអនុម័ត', 'published' => 'បានផ្សាយ', 'archived' => 'ឯកសារ', 'trash' => 'ធុងសម្រាម',
    ];
    $currentStatus = request('status', '');
@endphp
<x-admin.layout title="ការគ្រប់គ្រងទំព័រ">
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <form method="GET" class="flex flex-wrap gap-2">
            @if ($currentStatus)<input type="hidden" name="status" value="{{ $currentStatus }}">@endif
            <input type="text" name="search" value="{{ request('search') }}" placeholder="ស្វែងរកចំណងជើង ឬ slug..."
                   class="bg-white border border-line rounded-[8px] h-10 px-4 text-[13px] w-[240px]">
            <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-10 px-5">ស្វែងរក</button>
        </form>

        @can('create', \App\Models\Page::class)
            <a href="{{ route('admin.pages.create') }}" class="bg-brand-green text-white font-bold text-[13px] rounded-[8px] h-10 px-5 flex items-center">+ បង្កើតទំព័រ</a>
        @endcan
    </div>

    <div class="flex flex-wrap gap-2 mb-4">
        @foreach ($statusTabs as $value => $label)
            <a href="{{ route('admin.pages.index', array_filter(['status' => $value, 'search' => request('search')])) }}"
               class="border border-line rounded-full px-3 py-1 text-[12px] font-bold {{ $currentStatus === $value ? 'bg-brand-blue text-white border-brand-blue' : 'text-ink' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <x-admin.data-table :headers="['ចំណងជើង', 'Slug', 'Template', 'ស្ថានភាព', 'ម៉ឺនុយ', 'កែប្រែចុងក្រោយ', 'អ្នកនិពន្ធ', '']">
        @forelse ($pages as $page)
            <tr>
                <td class="px-4 py-3 font-bold text-ink text-[14px] max-w-[240px] truncate">{{ $page->title_km }}</td>
                <td class="px-4 py-3 text-[12px] text-muted font-mono">{{ $page->slug }}</td>
                <td class="px-4 py-3 text-[12px] text-ink">{{ $page->template }}</td>
                <td class="px-4 py-3">
                    @if ($page->trashed())
                        <span class="bg-brand-red/10 text-brand-red inline-flex items-center rounded-full px-3 py-1 text-[12px] font-bold">ធុងសម្រាម</span>
                    @else
                        <x-admin.status-badge :status="$page->status" />
                    @endif
                </td>
                <td class="px-4 py-3 text-[12px] text-ink">
                    @if ($page->menuItems->isNotEmpty())
                        @foreach ($page->menuItems as $item)
                            <span class="bg-brand-blue-tint text-brand-blue text-[10px] font-bold rounded-full px-2 py-[2px] mr-1">{{ $item->menu->name }}</span>
                        @endforeach
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-[12px] text-muted">{{ $page->updated_at->format('d-m-Y') }}</td>
                <td class="px-4 py-3 text-[13px] text-ink">{{ $page->author?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    @if ($page->trashed())
                        @can('restore', $page)
                            <form method="POST" action="{{ route('admin.pages.restore', $page) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-brand-blue text-[13px] font-bold">សង្គ្រោះ</button>
                            </form>
                        @endcan
                        @can('forceDelete', $page)
                            <form method="POST" action="{{ route('admin.pages.force-delete', $page) }}" onsubmit="return confirm('លុបទំព័រនេះជាអចិន្ត្រៃយ៍? សកម្មភាពនេះមិនអាចត្រឡប់វិញបានទេ។')" class="inline ml-2">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-brand-red text-[13px] font-bold">លុបជាអចិន្ត្រៃយ៍</button>
                            </form>
                        @endcan
                    @else
                        <a href="{{ route('admin.pages.preview', $page) }}" target="_blank" class="text-muted text-[13px] font-bold mr-2">មើលសាកល្បង</a>
                        @can('create', \App\Models\Page::class)
                            <form method="POST" action="{{ route('admin.pages.duplicate', $page) }}" class="inline mr-2">
                                @csrf
                                <button type="submit" class="text-muted text-[13px] font-bold">ចម្លង</button>
                            </form>
                        @endcan
                        @can('update', $page)
                            <a href="{{ route('admin.pages.edit', $page) }}" class="text-brand-blue text-[13px] font-bold mr-2">កែប្រែ →</a>
                        @endcan
                        @can('delete', $page)
                            <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" onsubmit="return confirm('ទុកទំព័រនេះក្នុងធុងសម្រាម?')" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-brand-red text-[13px] font-bold">លុប</button>
                            </form>
                        @endcan
                    @endif
                </td>
            </tr>
        @empty
            <x-admin.empty-state message="មិនមានទំព័រទេ។" />
        @endforelse
    </x-admin.data-table>

    <div class="mt-4">{{ $pages->links() }}</div>
</x-admin.layout>
