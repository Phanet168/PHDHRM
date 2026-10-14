<x-admin.layout :title="($leader->exists ? 'កែសម្រួល' : 'បន្ថែម').'ថ្នាក់ដឹកនាំ'">
    @include('admin.about._nav')

    <form method="POST" action="{{ $leader->exists ? route('admin.about.leaders.update', $leader) : route('admin.about.leaders.store') }}"
          class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        @csrf
        @if ($leader->exists) @method('PUT') @endif

        <div class="xl:col-span-2 bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4">
            <div class="flex items-center justify-between gap-3">
                <p class="font-bold text-brand-navy text-[16px]">ព័ត៌មានថ្នាក់ដឹកនាំ</p>
                @if ($leader->is_demo)<span class="bg-amber-100 text-amber-700 rounded-full px-3 py-1 text-[12px] font-bold">DEMO — សូមជំនួសដោយឈ្មោះពិត</span>@endif
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.form-input name="name" label="ឈ្មោះ (ខ្មែរ)" :value="$leader->name" maxlength="255" required />
                <x-admin.form-input name="name_en" label="ឈ្មោះ (អង់គ្លេស)" :value="$leader->name_en" maxlength="255" />
                <x-admin.form-input name="title" label="តួនាទី (ខ្មែរ)" :value="$leader->title" maxlength="255" required />
                <x-admin.form-input name="title_en" label="តួនាទី (អង់គ្លេស)" :value="$leader->title_en" maxlength="255" />
            </div>
            <x-admin.textarea name="bio" label="ប្រវត្តិរូបសង្ខេប (ស្រេចចិត្ត)" :value="$leader->bio" rows="3" maxlength="1000" />
            <x-admin.form-input name="sort_order" label="លំដាប់" type="number" min="0" :value="$leader->sort_order" class="max-w-[200px]" />
            <div class="flex flex-wrap gap-6">
                <label class="flex items-center gap-2 text-[13px] font-bold text-ink"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $leader->is_active ?? true)) class="rounded border-line"> បើកប្រើ</label>
                <label class="flex items-center gap-2 text-[13px] font-bold text-ink"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $leader->is_featured)) class="rounded border-line"> លេចធ្លោ (Featured)</label>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('admin.about.leaders.index') }}" class="text-muted text-[14px] font-bold px-4 py-2">បោះបង់</a>
                <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6">រក្សាទុក</button>
            </div>
        </div>

        <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-3"
             x-data="{ mediaUrl: @js($leader->portraitUrl()) }"
             @change="if ($event.target.type === 'radio') { mediaUrl = $event.target.value === '' ? null : ($event.target.closest('label')?.querySelector('img')?.getAttribute('src') ?? mediaUrl) }">
            <p class="font-bold text-brand-navy text-[15px]">រូបថត</p>
            {{-- Same crop as the public card: 6:7, anchored to the top so heads are never cut. --}}
            <template x-if="mediaUrl"><img :src="mediaUrl" alt="" class="w-full max-w-[240px] aspect-[6/7] object-cover object-top rounded-[8px] border border-line"></template>
            <template x-if="!mediaUrl"><div class="w-full max-w-[240px] aspect-[6/7] rounded-[8px] border border-dashed border-line flex items-center justify-center text-muted"><x-news-icon name="user" width="48" height="48" /></div></template>
            @include('admin.pages._media-picker', ['name' => 'portrait_media_id', 'selected' => $leader->portrait_media_id, 'defaultFolderId' => $folderId])
            <p class="text-muted text-[11px]">ណែនាំ៖ រូបបញ្ឈរ (ឧ. 600×700 px) ដែលក្បាលនៅផ្នែកខាងលើ។ គ្មានរូប → បង្ហាញរូបតំណាងលំនាំដើម។</p>
        </div>
    </form>
</x-admin.layout>
