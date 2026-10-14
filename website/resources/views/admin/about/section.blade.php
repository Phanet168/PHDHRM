@php
    $card = 'bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4';
    $imageUrl = $key === 'hero' ? \App\Models\PageBanner::urlFor('about') : ($section->media?->isImage() ? $section->media->url : null);
    $imageMedia = $key === 'hero' ? $banner?->media : $section->media;
@endphp
<x-admin.layout :title="'អំពីមន្ទីរ · '.\App\Models\AboutSection::KEYS[$key]">
    @include('admin.about._nav')

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <form method="POST" action="{{ route('admin.about.sections.update', $key) }}" class="xl:col-span-2 {{ $card }}">
            @csrf @method('PUT')
            <div class="flex items-center justify-between gap-3">
                <p class="font-bold text-brand-navy text-[16px]">{{ $key === 'hero' ? 'ផ្ទាំងខាងលើ (Hero)' : 'ប្រវត្តិ និងបេសកកម្ម' }}</p>
                @if ($section->is_demo)<span class="bg-amber-100 text-amber-700 rounded-full px-3 py-1 text-[12px] font-bold">DEMO — ខ្លឹមសារគំរូ</span>@endif
            </div>

            <x-admin.form-input name="title" label="ចំណងជើង" :value="$section->title ?: ($key === 'hero' ? 'អំពីមន្ទីរ' : 'ប្រវត្តិ និងបេសកកម្ម')" required />

            @if ($key === 'hero')
                <x-admin.textarea name="subtitle" label="ចំណងជើងរង" :value="$section->subtitle" rows="2" maxlength="500" />
                <x-admin.form-input name="body" label="សម្រង់ខ្លី (ស្រេចចិត្ត — បង្ហាញខាងស្ដាំនៃ Hero)" :value="$section->body" maxlength="300" />
            @else
                <x-admin.rich-editor name="body" label="ខ្លឹមសារ" :value="$section->body" rows="10" />
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-admin.select name="image_position" label="ទីតាំងរូបភាព (Desktop)" :options="['right' => 'ខាងស្ដាំ', 'left' => 'ខាងឆ្វេង']" :value="$section->image_position ?? 'right'" :placeholder="null" />
                    <x-admin.form-input name="sort_order" label="លំដាប់" type="number" min="0" :value="$section->sort_order ?? 0" />
                </div>
            @endif

            <div x-data="{ mediaUrl: @js($imageUrl) }" @change="if ($event.target.type === 'radio') { mediaUrl = $event.target.value === '' ? null : ($event.target.closest('label')?.querySelector('img')?.getAttribute('src') ?? mediaUrl) }" class="flex flex-col gap-2">
                <p class="text-ink text-[13px] font-bold">{{ $key === 'hero' ? 'រូបភាព Hero (ប្រើរួមជាមួយ «ផ្ទាំងបដាទំព័រ»)' : 'រូបភាព' }}</p>
                <template x-if="mediaUrl"><img :src="mediaUrl" alt="" class="w-full max-w-[520px] aspect-[16/7] object-cover rounded-[8px] border border-line"></template>
                <template x-if="!mediaUrl"><div class="w-full max-w-[520px] aspect-[16/7] rounded-[8px] border border-dashed border-line flex items-center justify-center text-muted text-[12px]">{{ $key === 'hero' ? 'កំពុងប្រើរូបលំនាំដើម' : 'គ្មានរូបភាព' }}</div></template>
                @if ($imageMedia)<p class="text-muted text-[11px]">{{ $imageMedia->original_filename }} @if ($imageMedia->dimensions()) · {{ $imageMedia->dimensions() }} @endif · {{ $imageMedia->formattedSize() }}</p>@endif
                @include('admin.pages._media-picker', ['name' => 'media_id', 'selected' => $key === 'hero' ? $banner?->media_id : $section->media_id, 'defaultFolderId' => $folderId])
            </div>

            <label class="flex items-center gap-2 text-[13px] font-bold text-ink">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $section->exists ? $section->is_active : true)) class="rounded border-line">
                បើកប្រើ (បង្ហាញនៅលើទំព័រសាធារណៈ)
            </label>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('admin.about.index') }}" class="text-muted text-[14px] font-bold px-4 py-2">ត្រឡប់</a>
                <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6">រក្សាទុក</button>
            </div>
        </form>

        <div class="flex flex-col gap-6">
            @if ($mission)
                <form method="POST" action="{{ route('admin.about.sections.update', 'mission') }}" class="{{ $card }}">
                    @csrf @method('PUT')
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-bold text-brand-navy text-[16px]">សារបេសកកម្ម (Mission highlight)</p>
                        @if ($mission->is_demo)<span class="bg-amber-100 text-amber-700 rounded-full px-3 py-1 text-[12px] font-bold">DEMO</span>@endif
                    </div>
                    {{-- Plain fields with their own ids: this form shares the page with the history form. --}}
                    @php $field = 'bg-white border border-line rounded-[8px] px-[14px] text-[14px] text-ink w-full'; @endphp
                    <div><label for="mission_title" class="block text-ink text-[13px] font-bold mb-1">ចំណងជើង <span class="text-brand-red">*</span></label>
                        <input id="mission_title" name="title" value="{{ $mission->title ?: 'បេសកកម្ម' }}" required maxlength="255" class="{{ $field }} h-[44px]"></div>
                    <div><label for="mission_body" class="block text-ink text-[13px] font-bold mb-1">សារ <span class="text-brand-red">*</span></label>
                        <textarea id="mission_body" name="body" rows="4" required maxlength="1000" class="{{ $field }} py-3">{{ $mission->body }}</textarea></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label for="mission_icon" class="block text-ink text-[13px] font-bold mb-1">រូបតំណាង</label>
                            <select id="mission_icon" name="icon_key" class="{{ $field }} h-[44px]">@foreach (\App\Support\AboutIcon::ICONS as $iconKey => $iconLabel)<option value="{{ $iconKey }}" @selected(($mission->icon_key ?? 'target') === $iconKey)>{{ $iconLabel }}</option>@endforeach</select></div>
                        <div><label for="mission_sort" class="block text-ink text-[13px] font-bold mb-1">លំដាប់</label>
                            <input id="mission_sort" name="sort_order" type="number" min="0" value="{{ $mission->sort_order ?? 0 }}" class="{{ $field }} h-[44px]"></div>
                    </div>
                    <label class="flex items-center gap-2 text-[13px] font-bold text-ink">
                        <input type="checkbox" name="is_active" value="1" @checked($mission->exists ? $mission->is_active : true) class="rounded border-line">
                        បើកប្រើ
                    </label>
                    <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6 self-end">រក្សាទុក</button>
                </form>
            @endif

            <div class="{{ $card }}">
                <p class="font-bold text-brand-navy text-[15px]">ព័ត៌មាន</p>
                <p class="text-muted text-[12px]">កែចុងក្រោយ៖ {{ $section->updated_at?->format('d/m/Y H:i') ?? '—' }} @if ($section->editor) ដោយ {{ $section->editor->name }} @endif</p>
                @if ($key === 'hero')
                    <p class="text-muted text-[12px]">រូប Hero គឺជារូបដដែលនៃ «ផ្ទាំងបដាទំព័រ › អំពីមន្ទីរ» — មិនមានច្បាប់ចម្លងទីពីរទេ។ ទំហំណែនាំ៖ យ៉ាងតិច 1600×500 px។</p>
                @else
                    <p class="text-muted text-[12px]">ខ្លឹមសារ HTML ត្រូវបានសម្អាត (sanitize) មុនរក្សាទុក។ ប្រសិនបើបិទប្រើ ផ្នែកនេះនឹងមិនបង្ហាញលើទំព័រសាធារណៈទេ។</p>
                @endif
            </div>
        </div>
    </div>
</x-admin.layout>
