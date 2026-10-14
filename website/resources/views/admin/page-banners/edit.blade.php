<x-admin.layout :title="'ផ្ទាំងបដា៖ '.\App\Models\PageBanner::label($pageKey)">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        {{-- Live preview, drawn the same way the public <x-page-banner> is (260px tall,
             object-cover, navy overlay) so the admin sees the real crop. --}}
        <div class="lg:col-span-2 bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-3"
             x-data="{ previewUrl: @js($currentUrl) }"
             @change="if ($event.target.type === 'radio' && $event.target.value !== '') { previewUrl = $event.target.closest('label')?.querySelector('img')?.getAttribute('src') ?? previewUrl }">
            <p class="font-bold text-brand-navy text-[15px]">ការមើលជាមុន</p>

            <div class="relative h-[260px] overflow-hidden rounded-[8px] border border-line">
                <img :src="previewUrl" alt="" class="absolute inset-0 size-full object-cover">
                <div class="absolute inset-0 bg-brand-navy opacity-[0.58]"></div>
                <span class="absolute left-5 top-[90px] font-bold text-[28px] text-white">{{ \App\Models\PageBanner::label($pageKey) }}</span>
            </div>

            @if ($banner->media)
                <p class="text-muted text-[11px]">
                    {{ $banner->media->original_filename }}
                    @if ($banner->media->dimensions()) · {{ $banner->media->dimensions() }} @endif
                    · {{ $banner->media->formattedSize() }}
                </p>
            @else
                <p class="text-muted text-[11px]">កំពុងប្រើរូបភាពលំនាំដើមរបស់ទំព័រ។</p>
            @endif

            <p class="text-muted text-[12px]">
                ទំហំណែនាំ៖ យ៉ាងតិច 1600×500 px (ផ្ដេក)។ បដាមានកម្ពស់ថេរ 260px ហើយរូបភាពត្រូវបានកាត់ឲ្យពេញ ដូច្នេះគួរជៀសវាងអត្ថបទ ឬមុខមនុស្សនៅគែម។
            </p>

            <form method="POST" action="{{ route('admin.page-banners.update', $pageKey) }}" enctype="multipart/form-data" class="flex flex-col gap-4">
                @csrf @method('PUT')

                <div>
                    <p class="text-ink text-[13px] font-bold mb-1">ជ្រើសរើសពី Media Library</p>
                    @include('admin.pages._media-picker', ['name' => 'media_id', 'selected' => $banner->media_id, 'defaultFolderId' => $folderId])
                    @error('media_id')<p class="text-brand-red text-[12px] mt-1">{{ $message }}</p>@enderror
                </div>

                <x-admin.upload-field name="banner_file" label="ឬ Upload រូបភាពថ្មី (jpg / png / webp, ≤ 5MB)" accept="image/jpeg,image/png,image/webp" />

                <label class="flex items-center gap-2 text-[13px] font-bold text-ink">
                    <input type="checkbox" name="reset_to_default" value="1">
                    ត្រឡប់ទៅប្រើរូបភាពលំនាំដើម
                </label>

                <div class="flex justify-end gap-3 pt-2">
                    <a href="{{ route('admin.page-banners.index') }}" class="text-muted text-[14px] font-bold px-4 py-2">ត្រឡប់</a>
                    <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6">រក្សាទុក</button>
                </div>
            </form>
        </div>

        <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-2">
            <p class="font-bold text-brand-navy text-[15px]">ព័ត៌មានទំព័រ</p>
            <p class="text-ink text-[13px]">{{ \App\Models\PageBanner::label($pageKey) }}</p>
            @if (\App\Models\PageBanner::PAGES[$pageKey]['route'])
                <a href="{{ route(\App\Models\PageBanner::PAGES[$pageKey]['route']) }}" target="_blank" rel="noopener" class="text-brand-blue text-[13px] font-bold">មើលទំព័រសាធារណៈ ↗</a>
            @else
                <p class="text-muted text-[12px]">បដានេះប្រើលើទំព័រប្រភេទអត្ថបទទាំងអស់។</p>
            @endif
            <p class="text-muted text-[12px] mt-2">រូបដែល Upload ថ្មី ត្រូវបានរក្សាទុកក្នុងថតរបស់ទំព័រនេះនៅ Media Library។</p>
        </div>
    </div>
</x-admin.layout>
