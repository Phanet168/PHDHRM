<x-admin.layout :title="($unit->exists ? 'កែសម្រួល' : 'បន្ថែម').'អង្គភាព'">
    @include('admin.about._nav')

    <form method="POST" action="{{ $unit->exists ? route('admin.about.organization.update', $unit) : route('admin.about.organization.store') }}"
          class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4 max-w-[860px]">
        @csrf
        @if ($unit->exists) @method('PUT') @endif

        <div class="flex items-center justify-between gap-3">
            <p class="font-bold text-brand-navy text-[16px]">{{ $unit->exists ? 'កែសម្រួល' : 'បន្ថែម' }}អង្គភាព</p>
            @if ($unit->is_demo)<span class="bg-amber-100 text-amber-700 rounded-full px-3 py-1 text-[12px] font-bold">DEMO — សូមជំនួសដោយអង្គភាពពិត</span>@endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-admin.form-input name="name" label="ឈ្មោះអង្គភាព" :value="$unit->name" maxlength="255" required />
            <x-admin.select name="type" label="ប្រភេទ (ក្រុមបង្ហាញ)" :options="\App\Models\AboutOrgUnit::TYPES" :value="$unit->type" :placeholder="null" required />
            <x-admin.select name="parent_id" label="ស្ថិតក្រោមអង្គភាព (ស្រេចចិត្ត)" :options="$parents" :value="$unit->parent_id" placeholder="— កម្រិតកំពូល —" />
            <x-admin.select name="facility_id" label="ភ្ជាប់ទៅមូលដ្ឋានសុខាភិបាល (ស្រេចចិត្ត)" :options="$facilities" :value="$unit->facility_id" placeholder="— មិនភ្ជាប់ —" />
        </div>
        <p class="text-muted text-[12px] -mt-2">ពេលភ្ជាប់ទៅមូលដ្ឋានសុខាភិបាលដែលបានផ្សាយ ឈ្មោះលើទំព័រសាធារណៈនឹងក្លាយជាតំណទៅទំព័រមូលដ្ឋាននោះ។</p>
        <x-admin.textarea name="description" label="ការពិពណ៌នា (ស្រេចចិត្ត)" :value="$unit->description" rows="3" maxlength="1000" />
        <x-admin.form-input name="sort_order" label="លំដាប់" type="number" min="0" :value="$unit->sort_order" class="max-w-[200px]" />

        <label class="flex items-center gap-2 text-[13px] font-bold text-ink">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $unit->is_active ?? true)) class="rounded border-line">
            បើកប្រើ
        </label>

        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('admin.about.organization.index') }}" class="text-muted text-[14px] font-bold px-4 py-2">បោះបង់</a>
            <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6">រក្សាទុក</button>
        </div>
    </form>
</x-admin.layout>
