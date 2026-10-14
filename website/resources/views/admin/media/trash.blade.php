<x-admin.layout title="ធុងសម្រាម - Media Library">
    <div class="media-app">
        <div class="media-page-header">
            <div>
                <p class="media-page-title">ធុងសម្រាម</p>
                <p class="media-page-subtitle">ថត និងឯកសារដែលបានលុប — អាចសង្គ្រោះ ឬលុបជាអចិន្ត្រៃយ៍</p>
            </div>
            <div class="media-page-header-actions">
                <a href="{{ route('admin.media.index') }}" class="media-btn media-btn-outline">← ត្រឡប់ទៅ Media Library</a>
                @if ($folders->isNotEmpty() || $media->isNotEmpty())
                    <form method="POST" action="{{ route('admin.media.empty-trash') }}" onsubmit="return confirm('លុបធាតុទាំងអស់ក្នុងធុងសម្រាមជាអចិន្ត្រៃយ៍? សកម្មភាពនេះមិនអាចត្រឡប់វិញបានទេ។')">
                        @csrf
                        <button type="submit" class="media-btn" style="background:var(--color-brand-red);color:white;">សម្អាតធុងសម្រាម</button>
                    </form>
                @endif
            </div>
        </div>

        @if ($folders->isEmpty() && $media->isEmpty())
            <div class="media-empty-state" style="background:white;border:1px solid var(--color-line);border-radius:10px;">
                <x-news-icon name="trash" width="40" height="40" />
                <p>ធុងសម្រាមទទេ។</p>
            </div>
        @else
            <table class="media-list-table" style="background:white;border:1px solid var(--color-line);border-radius:10px;overflow:hidden;">
                <thead>
                    <tr><th>ឈ្មោះ</th><th>ប្រភេទ</th><th>ថតដើម</th><th>បានលុបនៅ</th><th>សកម្មភាព</th></tr>
                </thead>
                <tbody>
                    @foreach ($folders as $folder)
                        <tr>
                            <td class="flex items-center gap-2"><x-media-folder-icon :size="20" /> {{ $folder->name }}</td>
                            <td>ថត</td>
                            <td>{{ $folder->parent?->name ?? 'Media' }}</td>
                            <td>{{ $folder->deleted_at->format('d-m-Y H:i') }}</td>
                            <td>
                                <div class="flex items-center gap-3">
                                    @can('restore', $folder)
                                        <form method="POST" action="{{ route('admin.media.folders.restore', $folder) }}">
                                            @csrf
                                            <button type="submit" class="text-brand-blue text-[12px] font-bold">សង្គ្រោះ</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.media.folders.force-delete', $folder) }}" onsubmit="return confirm('លុបថតនេះ (និងខ្លឹមសាររបស់វា) ជាអចិន្ត្រៃយ៍? សកម្មភាពនេះមិនអាចត្រឡប់វិញបានទេ។')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-brand-red text-[12px] font-bold">លុបជាអចិន្ត្រៃយ៍</button>
                                        </form>
                                    @endcan
                                </div>
                                @if (session('confirm_permanent_delete_folder') == $folder->id)
                                    <div class="media-reference-warning mt-2">
                                        <p class="font-bold">{{ $errors->first('folder') }}</p>
                                        <form method="POST" action="{{ route('admin.media.folders.force-delete', $folder) }}" onsubmit="return confirm('ថតនេះមានឯកសារកំពុងប្រើប្រាស់នៅកន្លែងផ្សេង។ តើអ្នកចង់លុបទាំងអស់ក៏ដោយឬទេ?')" class="mt-2">
                                            @csrf @method('DELETE')
                                            <input type="hidden" name="force" value="1">
                                            <button type="submit" class="text-brand-red text-[11px] font-bold">លុបទាំងអស់ក៏ដោយ</button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @foreach ($media as $item)
                        <tr>
                            <td class="flex items-center gap-2">
                                <div class="media-list-thumb">
                                    @if ($item->isImage())
                                        <img src="{{ $item->url }}" alt="{{ $item->original_filename }}">
                                    @else
                                        <x-media-file-icon :type="$item->iconType()" :size="20" />
                                    @endif
                                </div>
                                {{ $item->original_filename }}
                            </td>
                            <td>{{ $item->typeLabel() }}</td>
                            <td>{{ $item->folder?->name ?? 'Media' }}</td>
                            <td>{{ $item->deleted_at->format('d-m-Y H:i') }}</td>
                            <td>
                                <div class="flex items-center gap-3">
                                    @can('restore', $item)
                                        <form method="POST" action="{{ route('admin.media.restore', $item) }}">
                                            @csrf
                                            <button type="submit" class="text-brand-blue text-[12px] font-bold">សង្គ្រោះ</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.media.force-delete', $item) }}" onsubmit="return confirm('លុបឯកសារនេះជាអចិន្ត្រៃយ៍? សកម្មភាពនេះមិនអាចត្រឡប់វិញបានទេ។')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-brand-red text-[12px] font-bold">លុបជាអចិន្ត្រៃយ៍</button>
                                        </form>
                                    @endcan
                                </div>
                                @if (session('confirm_permanent_delete_media') == $item->id)
                                    <div class="media-reference-warning mt-2">
                                        <p class="font-bold">ឯកសារនេះកំពុងត្រូវបានប្រើប្រាស់</p>
                                        @if (session('media_references'))
                                            <ul>
                                                @foreach (session('media_references') as $reference)
                                                    <li>{{ $reference['label'] }} ({{ $reference['count'] }})</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                        <form method="POST" action="{{ route('admin.media.force-delete', $item) }}" onsubmit="return confirm('ឯកសារនេះកំពុងប្រើប្រាស់នៅកន្លែងផ្សេង។ តើអ្នកចង់លុបជាអចិន្ត្រៃយ៍ទាំងអស់ក៏ដោយឬទេ?')" class="mt-2">
                                            @csrf @method('DELETE')
                                            <input type="hidden" name="force" value="1">
                                            <button type="submit" class="text-brand-red text-[11px] font-bold">លុបទាំងអស់ក៏ដោយ</button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-4">{{ $media->links() }}</div>
        @endif
    </div>
</x-admin.layout>
