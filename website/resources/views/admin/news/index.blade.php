<x-admin.layout title="ព័ត៌មាន និងសកម្មភាព">
    <div class="flex items-center gap-2 mb-4 border-b border-line">
        <a href="{{ route('admin.news.index', request()->except(['status', 'page'])) }}"
           class="px-4 py-2 text-[13px] font-bold border-b-2 {{ ! $isTrash ? 'text-brand-blue border-brand-blue' : 'text-muted border-transparent' }}">
            ព័ត៌មានទាំងអស់
        </a>
        @if ($trashedCount > 0 || $isTrash)
            <a href="{{ route('admin.news.index', array_merge(request()->except(['page']), ['status' => 'trash'])) }}"
               class="px-4 py-2 text-[13px] font-bold border-b-2 {{ $isTrash ? 'text-brand-red border-brand-red' : 'text-muted border-transparent' }}">
                ព័ត៌មានដែលបានលុប @if ($trashedCount > 0) ({{ $trashedCount }}) @endif
            </a>
        @endif
    </div>

    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        {{-- Category/Facility/Status are now filtered directly from the table header below (each
             select there carries form="news-filter-form" so it submits into this same form despite
             sitting inside the <table>) — only the free-text title search stays up here. --}}
        <form id="news-filter-form" method="GET" class="flex flex-wrap gap-2">
            @if ($isTrash)
                <input type="hidden" name="status" value="trash">
            @endif
            <input type="text" name="search" value="{{ request('search') }}" placeholder="ស្វែងរកចំណងជើង..."
                   class="bg-white border border-line rounded-[8px] h-10 px-4 text-[13px] w-[220px]">
            <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-10 px-5">ស្វែងរក</button>
        </form>

        @unless ($isTrash)
            @can('create', \App\Models\News::class)
                <a href="{{ route('admin.news.create') }}" class="bg-brand-green text-white font-bold text-[13px] rounded-[8px] h-10 px-5 flex items-center">+ បង្កើតព័ត៌មាន</a>
            @endcan
        @endunless
    </div>

    @if ($isTrash)
        <p class="text-muted text-[12px] mb-3">ព័ត៌មានទាំងនេះត្រូវបានលុបហើយ គឺមិនបង្ហាញក្នុងបញ្ជីសាធារណៈទេ។ រូបភាព និងឯកសារនៅ Media Library មិនត្រូវបានលុបតាមទេ។</p>
    @endif

    <x-admin.data-table>
        <x-slot:header>
            <x-admin.sortable-th sortKey="title" label="ចំណងជើង" :currentSort="$sort" :currentDirection="$direction" />

            <x-admin.sortable-th sortKey="category" label="ប្រភេទ" :currentSort="$sort" :currentDirection="$direction" :filterActive="request()->filled('category')">
                <select name="category" form="news-filter-form" onchange="this.form.submit()"
                        class="w-full bg-white border border-line rounded-[6px] h-8 px-2 text-[12px] text-ink">
                    <option value="">គ្រប់ប្រភេទ</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) request('category') === (string) $category->id)>{{ $category->name_km }}</option>
                    @endforeach
                </select>
            </x-admin.sortable-th>

            <x-admin.sortable-th sortKey="facility" label="មូលដ្ឋាន" :currentSort="$sort" :currentDirection="$direction" :filterActive="request()->filled('facility')">
                <select name="facility" form="news-filter-form" onchange="this.form.submit()"
                        class="w-full bg-white border border-line rounded-[6px] h-8 px-2 text-[12px] text-ink">
                    <option value="">គ្រប់មូលដ្ឋាន</option>
                    @foreach ($facilities as $facility)
                        <option value="{{ $facility->id }}" @selected((string) request('facility') === (string) $facility->id)>{{ $facility->name_km }}</option>
                    @endforeach
                </select>
            </x-admin.sortable-th>

            @if ($isTrash)
                <x-admin.sortable-th sortKey="deleted_at" label="បានលុបនៅ" :currentSort="$sort" :currentDirection="$direction" />
                <th class="px-4 py-3"></th>
            @else
                <x-admin.sortable-th sortKey="status" label="ស្ថានភាព" :currentSort="$sort" :currentDirection="$direction" :filterActive="request()->filled('status')">
                    <select name="status" form="news-filter-form" onchange="this.form.submit()"
                            class="w-full bg-white border border-line rounded-[6px] h-8 px-2 text-[12px] text-ink">
                        <option value="">គ្រប់ស្ថានភាព</option>
                        @foreach (['draft' => 'សេចក្តីព្រាង', 'pending_review' => 'រង់ចាំត្រួតពិនិត្យ', 'approved' => 'បានអនុម័ត', 'published' => 'បានផ្សាយ', 'archived' => 'ឯកសារ'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-admin.sortable-th>
                <x-admin.sortable-th sortKey="date" label="កាលបរិច្ឆេទ" :currentSort="$sort" :currentDirection="$direction" />
                <th class="px-4 py-3"></th>
            @endif
        </x-slot:header>

        @forelse ($news as $item)
            <tr>
                <td class="px-4 py-3 font-bold text-ink text-[14px] max-w-[280px] truncate">{{ $item->title_km }}</td>
                <td class="px-4 py-3 text-[13px] text-ink">{{ $item->category?->name_km ?? '—' }}</td>
                <td class="px-4 py-3 text-[13px] text-ink">{{ $item->facility?->name_km ?? 'ទូទៅ' }}</td>
                @if ($isTrash)
                    <td class="px-4 py-3 text-[12px] text-muted">{{ $item->deleted_at->format('d-m-Y H:i') }}</td>
                    <td class="px-4 py-3 text-right flex justify-end gap-3">
                        @can('restore', $item)
                            <x-admin.confirmation-dialog :action="route('admin.news.restore', $item)" message="សង្គ្រោះព័ត៌មាននេះមកវិញ?" label="សង្គ្រោះ" class="bg-white text-brand-blue border border-brand-blue" />
                        @endcan
                        @can('forceDelete', $item)
                            <x-admin.confirmation-dialog :action="route('admin.news.force-destroy', $item)" method="DELETE" message="លុបព័ត៌មាននេះជាអចិន្ត្រៃយ៍? សកម្មភាពនេះមិនអាចត្រឡប់វិញបានទេ។" label="លុបជាអចិន្ត្រៃយ៍" class="bg-white text-brand-red border border-brand-red" />
                        @endcan
                    </td>
                @else
                    <td class="px-4 py-3"><x-admin.status-badge :status="$item->status" /></td>
                    <td class="px-4 py-3 text-[12px] text-muted">{{ ($item->published_at ?? $item->updated_at)->format('d-m-Y') }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.news.edit', $item) }}" class="text-brand-blue text-[13px] font-bold">កែប្រែ →</a>
                    </td>
                @endif
            </tr>
        @empty
            <x-admin.empty-state :message="$isTrash ? 'ធុងសម្រាមទទេ។' : 'មិនមានព័ត៌មានទេ។'" />
        @endforelse
    </x-admin.data-table>

    <div class="mt-4">{{ $news->links() }}</div>
</x-admin.layout>
