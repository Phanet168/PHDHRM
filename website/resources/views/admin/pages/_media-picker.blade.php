@php
    $selected = $selected ?? null;
    $defaultFolderId = $defaultFolderId ?? '';
    $mimeType = $mimeType ?? 'image/%';
    $folderOptions = \App\Models\MediaFolder::optionsTree();
    $pickerMedia = \App\Models\Media::where('mime_type', 'like', $mimeType)->latest()->take(96)->get();

    // One query for every folder's parent_id (no N+1 per media item), used to
    // walk each item's ancestor chain — so selecting a PARENT folder also
    // reveals images sitting inside its sub-folders, at any depth.
    $folderParents = \App\Models\MediaFolder::pluck('parent_id', 'id');
    $ancestorChain = function (?int $folderId) use ($folderParents): array {
        $chain = [];
        while ($folderId !== null) {
            $chain[] = (string) $folderId;
            $folderId = $folderParents[$folderId] ?? null;
        }

        return $chain;
    };
@endphp
<details class="border border-line rounded-[8px] p-3" x-data="{ folder: @js((string) $defaultFolderId) }">
    <summary class="text-brand-blue text-[13px] font-bold cursor-pointer">ជ្រើសរើសពី Media Library ដើម្បីជៀសវាងឯកសារស្ទួន</summary>

    {{-- Windows-Explorer-style folder browsing (create folder/sub-folder, move, rename…) already
         lives on the full Media Library page — this just opens it (new tab, current folder kept
         in sync), instead of duplicating that functionality here. --}}
    <a href="{{ route('admin.media.index', $defaultFolderId ? ['folder' => $defaultFolderId] : []) }}" target="_blank" rel="noopener"
       class="inline-flex items-center gap-1 text-brand-blue text-[11px] font-bold mt-2">
        <x-news-icon name="external" width="12" height="12" /> បើក Media Library ពេញ (គ្រប់គ្រងថត)
    </a>

    @if (! empty($folderOptions))
        {{-- Filters the grid below to one folder (and its sub-folders) —
             client-side, since the images are already loaded. Backward
             compatible: "គ្រប់ថតទាំងអស់" (all folders) is the default and
             reproduces the previous flat-latest-images behavior exactly. --}}
        <select x-model="folder" class="mt-3 w-full h-9 border border-line rounded-[6px] px-2 text-[12px] text-ink">
            <option value="">គ្រប់ថតទាំងអស់ (ថ្មីៗបំផុត)</option>
            <option value="none">— គ្មានថត —</option>
            @foreach ($folderOptions as $folderId => $folderLabel)
                <option value="{{ $folderId }}">{{ $folderLabel }}</option>
            @endforeach
        </select>
        <p class="text-muted text-[11px] mt-1">ការជ្រើសរើសថតមេ នឹងបង្ហាញរូបភាពក្នុងថតរងផងដែរ។</p>
    @endif

    <div class="media-picker grid grid-cols-4 md:grid-cols-8 gap-2 max-h-[220px] overflow-y-auto mt-3">
        <label class="media-picker-item relative cursor-pointer" x-show="folder === ''">
            <input type="radio" name="{{ $name }}" value="" class="absolute top-1 left-1 z-10" @checked(! $selected)>
            <div class="h-[60px] w-full rounded-[6px] border border-dashed border-line flex items-center justify-center text-[10px] text-muted">គ្មាន</div>
        </label>
        @foreach ($pickerMedia as $media)
            <label class="media-picker-item relative cursor-pointer"
                x-show="folder === '' || (folder === 'none' && {{ $media->folder_id ? 'false' : 'true' }}) || @js($ancestorChain($media->folder_id)).includes(folder)">
                <input type="radio" name="{{ $name }}" value="{{ $media->id }}" class="absolute top-1 left-1 z-10" @checked((string) $selected === (string) $media->id)>
                @if ($media->isImage())
                    <img src="{{ $media->url }}" title="{{ $media->original_filename }}" class="h-[60px] w-full object-cover rounded-[6px] border border-line">
                @else
                    <div title="{{ $media->original_filename }}" class="h-[60px] w-full rounded-[6px] border border-line bg-brand-navy/5 flex flex-col items-center justify-center text-[10px] text-muted gap-0.5 px-1">
                        <span>▶</span>
                        <span class="truncate w-full text-center">{{ $media->original_filename }}</span>
                    </div>
                @endif
            </label>
        @endforeach
    </div>
</details>
