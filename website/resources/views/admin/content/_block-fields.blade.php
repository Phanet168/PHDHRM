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
        <x-admin.upload-field name="image_file" label="រូបភាពថ្មី (ជ្រើសរើសឯកសារ ឬប្រើ Media Library ខាងក្រោម)" :current="$v('image')" />

        @include('admin.pages._media-picker', [
            'name' => 'data[media_id]',
            'selected' => $v('media_id'),
            'defaultFolderId' => $contentFolderId ?? null,
        ])

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <x-admin.form-input name="data[caption]" label="ចំណងជើងរូបភាព" :value="$v('caption')" />
            <x-admin.form-input name="data[alt_text]" label="Alt Text" :value="$v('alt_text')" />
        </div>
        <x-admin.form-input name="data[link_url]" label="តំណភ្ជាប់ (ស្រេចចិត្ត)" :value="$v('link_url')" />
        @break

    @case('gallery')
        <p class="text-ink text-[13px] font-bold mb-1">ជ្រើសរើសរូបភាពពីវិចិត្រសាលអត្ថបទ (ទុកទទេ = បង្ហាញទាំងអស់)</p>
        <div class="grid grid-cols-3 md:grid-cols-6 gap-2">
            @foreach ($content->images as $image)
                <label class="relative">
                    <input type="checkbox" name="data[image_ids][]" value="{{ $image->id }}" @checked(in_array($image->id, $v('image_ids', []) ?? [])) class="absolute top-1 left-1 rounded">
                    <img src="{{ $image->url() }}" class="h-[70px] w-full object-cover rounded-[6px] border border-line">
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

    @case('html')
        <x-admin.textarea name="data[html]" label="HTML (នឹងត្រូវបានសម្អាតសុវត្ថិភាព)" :value="$v('html')" rows="8" />
        @break

    @case('html_css')
        @can('useCustomHtml', \App\Models\Content::class)
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
