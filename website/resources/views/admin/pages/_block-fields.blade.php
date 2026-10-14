@php
    $values = $values ?? [];
    $v = fn (string $key, $default = null) => data_get($values, $key, $default);
@endphp

@switch($type)
    @case('heading')
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div class="md:col-span-3"><x-admin.form-input name="data[text]" label="អត្ថបទចំណងជើង" :value="$v('text')" required /></div>
            <x-admin.select name="data[level]" label="កម្រិត" :value="$v('level', 'h2')" :placeholder="null" :options="['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4']" />
        </div>
        @break

    @case('text')
        <x-admin.textarea name="data[html]" label="អត្ថបទ (អាចប្រើ HTML មូលដ្ឋាន)" :value="$v('html')" rows="6" />
        @break

    @case('image')
        <x-admin.upload-field name="image_file" label="រូបភាពថ្មី (ឬប្រើ Media Library ខាងក្រោម)" :current="$v('image')" />
        @include('admin.pages._media-picker', ['name' => 'data[media_id]', 'selected' => $v('media_id'), 'defaultFolderId' => $pageFolderId ?? null])
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <x-admin.form-input name="data[caption]" label="ចំណងជើងរូបភាព" :value="$v('caption')" />
            <x-admin.form-input name="data[alt_text]" label="Alt Text" :value="$v('alt_text')" />
        </div>
        <x-admin.form-input name="data[link_url]" label="តំណភ្ជាប់ (ស្រេចចិត្ត)" :value="$v('link_url')" />
        @break

    @case('image_text')
        <x-admin.upload-field name="image_file" label="រូបភាពថ្មី (ឬប្រើ Media Library ខាងក្រោម)" :current="$v('image')" />
        @include('admin.pages._media-picker', ['name' => 'data[media_id]', 'selected' => $v('media_id'), 'defaultFolderId' => $pageFolderId ?? null])
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <x-admin.form-input name="data[alt_text]" label="Alt Text" :value="$v('alt_text')" />
            <x-admin.select name="data[image_position]" label="ទីតាំងរូបភាព" :value="$v('image_position', 'left')" :placeholder="null" :options="['left' => 'ឆ្វេង', 'right' => 'ស្តាំ']" />
        </div>
        <x-admin.form-input name="data[heading]" label="ចំណងជើងតូច (ស្រេចចិត្ត)" :value="$v('heading')" />
        <x-admin.textarea name="data[html]" label="អត្ថបទ" :value="$v('html')" rows="5" />
        @break

    @case('gallery')
        <p class="text-ink text-[13px] font-bold mb-1">ជ្រើសរើសរូបភាពច្រើនពី Media Library</p>
        <div class="grid grid-cols-4 md:grid-cols-8 gap-2 max-h-[240px] overflow-y-auto">
            @foreach (\App\Models\Media::where('mime_type', 'like', 'image/%')->latest()->take(60)->get() as $media)
                <label class="relative cursor-pointer">
                    <input type="checkbox" name="data[media_ids][]" value="{{ $media->id }}" @checked(in_array($media->id, $v('media_ids', []) ?? [])) class="absolute top-1 left-1 z-10">
                    <img src="{{ $media->url }}" title="{{ $media->original_filename }}" class="h-[60px] w-full object-cover rounded-[6px] border border-line">
                </label>
            @endforeach
        </div>
        @break

    @case('button')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <x-admin.form-input name="data[text]" label="អត្ថបទប៊ូតុង" :value="$v('text', 'ចុចទីនេះ')" required />
            <x-admin.form-input name="data[url]" label="តំណភ្ជាប់" :value="$v('url')" required />
            <x-admin.select name="data[style]" label="រចនាប័ទ្ម" :value="$v('style', 'primary')" :placeholder="null" :options="['primary' => 'Primary', 'secondary' => 'Secondary', 'outline' => 'Outline']" />
            <x-admin.select name="data[target]" label="បើកនៅ" :value="$v('target', '_self')" :placeholder="null" :options="['_self' => 'ទំព័រដដែល', '_blank' => 'ផ្ទាំងថ្មី']" />
        </div>
        @break

    @case('two_columns')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <x-admin.textarea name="data[left_html]" label="ជួរឈរឆ្វេង" :value="$v('left_html')" rows="5" />
            <x-admin.textarea name="data[right_html]" label="ជួរឈរស្តាំ" :value="$v('right_html')" rows="5" />
        </div>
        @break

    @case('cards')
        <p class="text-ink text-[13px] font-bold">កាតព័ត៌មាន (រហូតដល់ ៦ — ទុកទទេជួរណាមួយប្រសិនបើមិនប្រើ)</p>
        @for ($i = 0; $i < 6; $i++)
            @php $card = $v("cards.$i", []); @endphp
            <div class="grid grid-cols-1 md:grid-cols-3 gap-2 border border-line rounded-[8px] p-2">
                <x-admin.form-input name="data[cards][{{ $i }}][title]" label="ចំណងជើងកាត {{ $i + 1 }}" :value="$card['title'] ?? ''" />
                <x-admin.form-input name="data[cards][{{ $i }}][text]" label="ខ្លឹមសារខ្លី" :value="$card['text'] ?? ''" />
                <x-admin.form-input name="data[cards][{{ $i }}][link_url]" label="តំណភ្ជាប់ (ស្រេចចិត្ត)" :value="$card['link_url'] ?? ''" />
            </div>
        @endfor
        @break

    @case('video')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <x-admin.form-input name="data[url]" label="URL វីដេអូ (YouTube / Vimeo / MP4)" :value="$v('url')" required />
            <x-admin.form-input name="data[caption]" label="ចំណងជើងវីដេអូ" :value="$v('caption')" />
        </div>
        @break

    @case('quote')
        <x-admin.textarea name="data[text]" label="អត្ថបទសម្រង់" :value="$v('text')" rows="3" required />
        <x-admin.form-input name="data[author]" label="អ្នកនិយាយ / ប្រភព" :value="$v('author')" />
        @break

    @case('statistics')
        <p class="text-ink text-[13px] font-bold">ស្ថិតិ / ការរាប់ (រហូតដល់ ៦ — ទុកទទេជួរណាមួយប្រសិនបើមិនប្រើ)</p>
        @for ($i = 0; $i < 6; $i++)
            @php $stat = $v("items.$i", []); @endphp
            <div class="grid grid-cols-1 md:grid-cols-3 gap-2 border border-line rounded-[8px] p-2">
                <x-admin.form-input name="data[items][{{ $i }}][value]" label="តម្លៃ {{ $i + 1 }}" :value="$stat['value'] ?? ''" />
                <x-admin.form-input name="data[items][{{ $i }}][suffix]" label="បច្ច័យ (ស្រេចចិត្ត, ឧ. +, %)" :value="$stat['suffix'] ?? ''" />
                <x-admin.form-input name="data[items][{{ $i }}][label]" label="ស្លាក" :value="$stat['label'] ?? ''" />
            </div>
        @endfor
        @break

    @case('contact_info')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <x-admin.form-input name="data[phone]" label="ទូរស័ព្ទ" :value="$v('phone')" />
            <x-admin.form-input name="data[email]" label="អ៊ីមែល" :value="$v('email')" />
            <x-admin.form-input name="data[hours]" label="ម៉ោងធ្វើការ" :value="$v('hours')" />
            <x-admin.form-input name="data[address]" label="អាសយដ្ឋាន" :value="$v('address')" />
        </div>
        @break

    @case('map')
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <x-admin.form-input name="data[lat]" label="Latitude" :value="$v('lat', 13.5259)" />
            <x-admin.form-input name="data[lng]" label="Longitude" :value="$v('lng', 105.9683)" />
            <x-admin.form-input name="data[zoom]" label="កម្រិតពង្រីក (Zoom)" :value="$v('zoom', 14)" />
            <x-admin.form-input name="data[marker_title]" label="ចំណងជើងម្ជុល" :value="$v('marker_title')" />
        </div>
        @break

    @case('html_css')
        @can('useCustomHtml', \App\Models\Page::class)
            <div class="flex flex-col gap-3">
                <div>
                    <label class="block text-ink text-[13px] font-bold mb-1">HTML</label>
                    <textarea name="data[html]" data-codemirror="html" rows="8" class="w-full border border-line rounded-[8px] text-[13px] font-mono p-3">{{ $v('html') }}</textarea>
                </div>
                <div>
                    <label class="block text-ink text-[13px] font-bold mb-1">CSS (នឹងកំណត់វិសាលភាពស្វ័យប្រវត្តិទៅតែ Block នេះ)</label>
                    <textarea name="data[css]" data-codemirror="css" rows="6" class="w-full border border-line rounded-[8px] text-[13px] font-mono p-3">{{ $v('css') }}</textarea>
                </div>
                <div>
                    <p class="text-ink text-[13px] font-bold mb-1">មើលមុន (Preview — គ្មានស្គ្រីបដំណើរការ)</p>
                    <iframe data-html-css-preview sandbox="allow-same-origin" class="w-full h-[160px] border border-line rounded-[8px] bg-white"></iframe>
                </div>
            </div>
        @else
            <p class="text-brand-red text-[13px]">អ្នកមិនមានសិទ្ធិកែប្រែ HTML + CSS ផ្ទាល់ខ្លួនទេ។</p>
        @endcan
        @break
@endswitch
