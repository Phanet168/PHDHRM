<x-admin.layout title="មូលដ្ឋានសុខាភិបាល">
    <div class="flex items-center justify-between mb-5">
        <form method="GET" class="flex flex-wrap gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="ស្វែងរកតាមឈ្មោះ ឬលេខកូដ..."
                   class="bg-white border border-line rounded-[8px] h-10 px-4 text-[13px] w-[260px]">
            <select name="type" class="bg-white border border-line rounded-[8px] h-10 px-3 text-[13px]">
                <option value="">គ្រប់ប្រភេទ</option>
                <option value="referral_hospital" @selected(request('type') === 'referral_hospital')>មន្ទីរពេទ្យបង្អែក</option>
                <option value="health_center" @selected(request('type') === 'health_center')>មណ្ឌលសុខភាព</option>
                <option value="health_post" @selected(request('type') === 'health_post')>ប៉ុស្តិ៍សុខភាព</option>
            </select>
            <select name="district" class="bg-white border border-line rounded-[8px] h-10 px-3 text-[13px]">
                <option value="">គ្រប់ស្រុក</option>
                @foreach ($districts as $district)
                    <option value="{{ $district }}" @selected(request('district') === $district)>{{ $district }}</option>
                @endforeach
            </select>
            <select name="status" class="bg-white border border-line rounded-[8px] h-10 px-3 text-[13px]">
                <option value="">គ្រប់ស្ថានភាព</option>
                <option value="draft" @selected(request('status') === 'draft')>សេចក្តីព្រាង</option>
                <option value="published" @selected(request('status') === 'published')>បានផ្សាយ</option>
                <option value="archived" @selected(request('status') === 'archived')>ឯកសារ</option>
            </select>
            <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-10 px-5">ស្វែងរក</button>
        </form>

        @can('create', \App\Models\Facility::class)
            <a href="{{ route('admin.facilities.create') }}" class="bg-brand-green text-white font-bold text-[13px] rounded-[8px] h-10 px-5 flex items-center">+ បន្ថែមមូលដ្ឋាន</a>
        @endcan
    </div>

    <x-admin.data-table :headers="['មូលដ្ឋាន', 'ប្រភេទ', 'ស្រុក', 'ស្ថានភាព', '']">
        @forelse ($facilities as $facility)
            <tr>
                <td class="px-4 py-3">
                    <p class="font-bold text-ink text-[14px]">{{ $facility->name_km }}</p>
                    <p class="text-muted text-[12px]">{{ $facility->code }}</p>
                </td>
                <td class="px-4 py-3 text-[13px] text-ink">{{ \App\Models\Facility::typeLabel($facility->facility_type) }}</td>
                <td class="px-4 py-3 text-[13px] text-ink">{{ $facility->district }}</td>
                <td class="px-4 py-3"><x-admin.status-badge :status="$facility->status" /></td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.facilities.edit', $facility) }}" class="text-brand-blue text-[13px] font-bold">កែប្រែ →</a>
                </td>
            </tr>
        @empty
            <x-admin.empty-state message="មិនមានមូលដ្ឋានសុខាភិបាលទេ។" />
        @endforelse
    </x-admin.data-table>

    <div class="mt-4">{{ $facilities->links() }}</div>
</x-admin.layout>
