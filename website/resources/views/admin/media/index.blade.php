<x-admin.layout title="Media Library">
    <div class="media-app"
         x-data="mediaExplorer({
             bulkActionUrl: @js(route('admin.media.bulk-action')),
             currentFolderId: @js($currentFolder?->id),
             referencesUrlTemplate: @js(route('admin.media.references', ['medium' => '__ID__'])),
             previewItems: @js($media->map(fn ($m) => [
                 'id' => $m->id, 'url' => $m->url, 'name' => $m->original_filename,
                 'type' => $m->iconType(), 'dims' => $m->dimensions(), 'size' => $m->formattedSize(),
             ])->values()),
         })"
         @keydown.window="handleGlobalKeydown($event)">

        <div class="media-page-header">
            <div>
                <p class="media-page-title">Media Library</p>
                <p class="media-page-subtitle">គ្រប់គ្រងរូបភាព ឯកសារ និងមាតិកាមេឌៀ</p>
            </div>
            <div class="media-page-header-actions">
                @can('create', \App\Models\MediaFolder::class)
                    <button type="button" class="media-btn media-btn-outline" @click="window.dispatchEvent(new CustomEvent('open-modal-create-folder'))">
                        <x-news-icon name="folder-plus" width="16" height="16" /> បង្កើតថតថ្មី
                    </button>
                @endcan
                @can('create', \App\Models\Media::class)
                    <button type="button" class="media-btn media-btn-primary" onclick="document.getElementById('media-upload-input').click()">
                        <x-news-icon name="upload" width="16" height="16" /> Upload Files
                    </button>
                @endcan
            </div>
        </div>

        {{-- Navigation toolbar: back / forward / up / refresh + breadcrumb + search --}}
        <div class="media-nav-toolbar">
            <div class="media-nav-buttons">
                <button type="button" class="media-nav-btn" @click="toggleTreeDrawer()" style="display:none" x-bind:style="window.innerWidth < 1024 ? '' : 'display:none'" title="ថតឯកសារ">
                    <x-news-icon name="grid" width="16" height="16" />
                </button>
                <button type="button" class="media-nav-btn" @click="goBack()" title="ថយក្រោយ">
                    <x-news-icon name="chevron-left" width="17" height="17" />
                </button>
                <button type="button" class="media-nav-btn" @click="goForward()" title="ទៅមុខ">
                    <x-news-icon name="chevron-right" width="17" height="17" />
                </button>
                @if ($currentFolder)
                    <a href="{{ route('admin.media.index', ['folder' => $currentFolder->parent_id]) }}" class="media-nav-btn" title="ថតមេ">
                        <x-news-icon name="arrow-up" width="17" height="17" />
                    </a>
                @else
                    <span class="media-nav-btn" style="opacity:.35;cursor:not-allowed;">
                        <x-news-icon name="arrow-up" width="17" height="17" />
                    </span>
                @endif
                <button type="button" class="media-nav-btn" onclick="window.location.reload()" title="ផ្ទុកឡើងវិញ">
                    <x-news-icon name="refresh" width="16" height="16" />
                </button>
            </div>

            <nav class="media-breadcrumb" :class="isDropTarget(null) ? 'is-drop-target' : ''"
                 @dragover="dragOverFolder($event, null)" @dragleave="dragLeaveFolder(null)" @drop="dropOnFolder($event, null)">
                <a href="{{ route('admin.media.index') }}">Media</a>
                @foreach ($breadcrumb as $crumb)
                    <span aria-hidden="true">/</span>
                    @if ($loop->last)
                        <span class="is-current">{{ $crumb->name }}</span>
                    @else
                        <a href="{{ route('admin.media.index', ['folder' => $crumb->id]) }}"
                           :class="isDropTarget({{ $crumb->id }}) ? 'is-drop-target' : ''"
                           @dragover="dragOverFolder($event, {{ $crumb->id }})" @dragleave="dragLeaveFolder({{ $crumb->id }})" @drop="dropOnFolder($event, {{ $crumb->id }})">{{ $crumb->name }}</a>
                    @endif
                @endforeach
            </nav>

            <form method="GET" id="media-filter-form" class="flex items-center gap-2 flex-wrap">
                <input type="hidden" name="folder" value="{{ $currentFolder?->id }}">
                <input type="hidden" name="view" value="{{ $view }}">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="search" name="q" value="{{ request('q') }}" class="media-search-input" placeholder="ស្វែងរកឯកសារ...">
                <select name="type" class="media-sort-select" onchange="this.form.submit()">
                    <option value="">គ្រប់ប្រភេទ</option>
                    <option value="image" @selected(request('type') === 'image')>រូបភាព</option>
                    <option value="document" @selected(request('type') === 'document')>ឯកសារ (DOC/XLS)</option>
                    <option value="pdf" @selected(request('type') === 'pdf')>PDF</option>
                    <option value="video" @selected(request('type') === 'video')>វីដេអូ</option>
                </select>
                <select name="usage" class="media-sort-select" onchange="this.form.submit()">
                    <option value="">ការប្រើប្រាស់ទាំងអស់</option>
                    <option value="used" @selected($usage === 'used')>កំពុងប្រើប្រាស់</option>
                    <option value="unused" @selected($usage === 'unused')>មិនទាន់ប្រើប្រាស់</option>
                </select>
            </form>
        </div>

        {{-- Action toolbar: select-all, cut/copy/paste/delete/move, view + sort --}}
        <div class="media-action-toolbar">
            <label class="media-select-all">
                <input type="checkbox" :checked="selectionCount > 0 && selectionCount === allKeys().length"
                       @change="$event.target.checked ? selectAll() : clearSelection()">
                ជ្រើសរើសទាំងអស់
            </label>
            <div class="media-nav-sep"></div>
            <button type="button" class="media-action-btn" :disabled="selectionCount === 0" @click="cutSelected()">
                <x-news-icon name="cut" width="15" height="15" /> កាត់
            </button>
            <button type="button" class="media-action-btn" :disabled="selectionCount === 0" @click="copySelected()">
                <x-news-icon name="copy" width="15" height="15" /> ចម្លង
            </button>
            <button type="button" class="media-action-btn" :disabled="!canPaste" @click="pasteClipboard()">
                <x-news-icon name="paste" width="15" height="15" /> បិទភ្ជាប់
            </button>
            <button type="button" class="media-action-btn is-danger" :disabled="selectionCount === 0" @click="bulkDeleteSelected()">
                <x-news-icon name="trash" width="15" height="15" /> លុប
            </button>
            <div class="media-nav-sep"></div>
            <template x-if="selectionCount > 0">
                <div class="flex items-center gap-2">
                    <select x-model="bulkMoveFolderId" class="media-sort-select">
                        <option value="">— ផ្លាស់ទីទៅ... —</option>
                        <option value="0">Media (ថតគល់)</option>
                        @foreach (\App\Models\MediaFolder::optionsTree() as $optionId => $optionLabel)
                            <option value="{{ $optionId }}">{{ $optionLabel }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="media-action-btn" :disabled="bulkMoveFolderId === ''" @click="bulkMoveSelected()">
                        <x-news-icon name="move" width="15" height="15" /> ផ្លាស់ទី
                    </button>
                </div>
            </template>

            <div class="media-action-toolbar-right">
                <select class="media-sort-select" onchange="window.location.href = this.value">
                    @php
                        $sortOptions = [
                            'name_asc' => 'តម្រៀប៖ A-Z',
                            'name_desc' => 'តម្រៀប៖ Z-A',
                            'latest' => 'តម្រៀប៖ ថ្មីៗបំផុត',
                            'oldest' => 'តម្រៀប៖ ចាស់ជាងគេ',
                            'size_desc' => 'តម្រៀប៖ ទំហំធំបំផុត',
                        ];
                    @endphp
                    @foreach ($sortOptions as $value => $label)
                        <option value="{{ route('admin.media.index', array_merge(request()->except('sort'), ['sort' => $value])) }}" @selected($sort === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="flex gap-1">
                    <a href="{{ route('admin.media.index', array_merge(request()->except('view'), ['view' => 'grid'])) }}" class="media-btn media-btn-outline {{ $view === 'grid' ? 'is-active' : '' }}" title="Grid">
                        <x-news-icon name="grid" width="15" height="15" />
                    </a>
                    <a href="{{ route('admin.media.index', array_merge(request()->except('view'), ['view' => 'list'])) }}" class="media-btn media-btn-outline {{ $view === 'list' ? 'is-active' : '' }}" title="List">
                        <x-news-icon name="list" width="15" height="15" />
                    </a>
                </div>
            </div>
        </div>

        @error('file')
            <p class="text-brand-red text-[13px]">{{ $message }}</p>
        @enderror

        <div class="media-panes">
            {{-- Folder tree pane --}}
            <aside class="media-tree-pane" :class="treeDrawerOpen ? 'is-drawer-open' : ''" @click.outside="treeDrawerOpen = false">
                <div class="media-tree-scroll">
                    <p class="media-tree-title">
                        <x-media-folder-icon :size="18" /> Media Library
                    </p>
                    <a href="{{ route('admin.media.index') }}" class="media-tree-link {{ ! $currentFolder ? 'is-active' : '' }}" style="margin-bottom:4px;"
                       :class="isDropTarget(null) ? 'is-drop-target' : ''"
                       @dragover="dragOverFolder($event, null)" @dragleave="dragLeaveFolder(null)" @drop="dropOnFolder($event, null)">
                        <x-media-folder-icon :size="15" class="media-tree-folder-icon" /> Media (ថតគល់)
                    </a>
                    @foreach ($folderTree as $folder)
                        @include('admin.media._folder-tree-item', ['folder' => $folder, 'currentFolderId' => $currentFolder?->id])
                    @endforeach

                    <a href="{{ route('admin.media.trash') }}" class="media-tree-trash-link">
                        <x-news-icon name="trash" width="15" height="15" /> ធុងសម្រាម
                    </a>
                </div>

                @if ($storage['disk_total'])
                    @php
                        $formatBytes = function ($bytes) {
                            $units = ['B', 'KB', 'MB', 'GB', 'TB'];
                            $i = 0;
                            while ($bytes >= 1024 && $i < count($units) - 1) { $bytes /= 1024; $i++; }
                            return round($bytes, 1) . ' ' . $units[$i];
                        };
                        $percent = min(100, round(($storage['used'] / $storage['disk_total']) * 100, 2));
                    @endphp
                    <div class="media-storage-card">
                        <p class="media-storage-label">ការប្រើប្រាស់ទំហំផ្ទុក</p>
                        <div class="media-storage-bar"><div class="media-storage-bar-fill" style="width: {{ max($percent, 1) }}%"></div></div>
                        <p class="media-storage-text">{{ $formatBytes($storage['used']) }} @if($storage['disk_free']) · នៅសល់ {{ $formatBytes($storage['disk_free']) }} @endif</p>
                    </div>
                @endif
            </aside>

            {{-- Main file pane --}}
            <main class="media-file-pane">
                <div class="media-file-scroll" id="media-file-pane" style="position:relative;"
                     data-upload-url="{{ route('admin.media.store') }}" data-folder-id="{{ $currentFolder?->id }}">
                    <input type="file" id="media-upload-input" multiple class="hidden" accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx,video/mp4,video/webm">
                    <div id="media-dropzone-overlay" class="media-dropzone-overlay" style="display:none;">
                        <x-news-icon name="upload" width="20" height="20" /> &nbsp;ទម្លាក់ឯកសារនៅទីនេះដើម្បីបញ្ចូល
                    </div>

                    @if ($folders->isEmpty() && $media->isEmpty())
                        <div class="media-empty-state">
                            <x-media-folder-icon :size="48" />
                            <p>មិនមានឯកសារ ឬថតទេ។ ចាប់ផ្តើមដោយបង្កើតថត ឬបញ្ចូលឯកសារ។</p>
                        </div>
                    @elseif ($view === 'grid')
                        <div class="media-grid">
                            @foreach ($folders as $folder)
                                @include('admin.media._folder-card', ['folder' => $folder])
                            @endforeach
                            @foreach ($media as $item)
                                @include('admin.media._media-card', ['item' => $item, 'isUsed' => isset($usedIds[$item->id])])
                            @endforeach
                        </div>
                        {{--
                            Modals render OUTSIDE the grid on purpose: each modal's root
                            element (from <x-admin.modal>) is a plain, class-less <div> with
                            no visible content. Rendered as a sibling of the cards inside a
                            CSS Grid container, each one of those invisible divs still counts
                            as its own grid item and consumes a cell in the row-major
                            auto-placement sequence — with folders having 2 modals each and
                            files having 3, that pushed every real card further and further
                            off its expected position, which is what caused cards to look
                            scattered across huge gaps instead of flowing left-to-right.
                            Alpine's modal pattern is triggered by a global window event, so
                            moving these out of the grid doesn't change how they're opened.
                        --}}
                        @foreach ($folders as $folder)
                            @include('admin.media._folder-card-modals', ['folder' => $folder])
                        @endforeach
                        @foreach ($media as $item)
                            @include('admin.media._media-card-modals', ['item' => $item])
                        @endforeach
                    @else
                        <table class="media-list-table">
                            <thead>
                                <tr>
                                    <th style="width:28px;"></th><th>ឈ្មោះ</th><th>ប្រភេទ</th><th>វិមាត្រ</th><th>ទំហំ</th><th>ថត</th><th>ប្រើប្រាស់</th><th>កែប្រែចុងក្រោយ</th><th>អ្នកបញ្ចូល</th><th>សកម្មភាព</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($folders as $folder)
                                    @include('admin.media._folder-row', ['folder' => $folder])
                                @endforeach
                                @foreach ($media as $item)
                                    @include('admin.media._media-row', ['item' => $item, 'isUsed' => isset($usedIds[$item->id])])
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    <div class="mt-4">{{ $media->links() }}</div>
                </div>

                <div class="media-status-bar">
                    <span x-show="selectionCount > 0">
                        <strong x-text="selectionCount"></strong> items selected &bull; <span x-text="formatBytes(selectedTotalSize())"></span>
                    </span>
                    <span x-show="selectionCount === 0">&nbsp;</span>
                    <span>{{ $folders->count() + $media->total() }} items</span>
                </div>
            </main>

            {{-- Details pane --}}
            <aside class="media-details-pane" :class="detailsSheetOpen ? 'is-sheet-open' : ''">
                <div x-show="selectionCount === 0">
                    <div class="media-details-empty">
                        <x-news-icon name="eye" width="28" height="28" />
                        <p class="mt-2">ជ្រើសរើសឯកសារ ដើម្បីមើលព័ត៌មានលម្អិត</p>
                    </div>
                </div>

                <div x-show="selectionCount > 1" x-cloak>
                    <div class="media-details-multi">
                        <p class="media-details-multi-count" x-text="selectionCount"></p>
                        <p class="media-details-multi-size">ធាតុត្រូវបានជ្រើសរើស · <span x-text="formatBytes(selectedTotalSize())"></span></p>
                    </div>
                </div>

                @foreach ($media as $item)
                    @include('admin.media._details-panel', ['item' => $item])
                @endforeach

                @foreach ($folders as $folder)
                    <div x-show="isDetailsFor('folder', {{ $folder->id }})" x-cloak>
                        <div class="media-details-preview"><x-media-folder-icon :size="64" /></div>
                        <p class="media-details-name">{{ $folder->name }}</p>
                        <dl>
                            <div class="media-details-row"><dt>ឯកសារ</dt><dd>{{ $folder->media_count }}</dd></div>
                            <div class="media-details-row"><dt>ថតរង</dt><dd>{{ $folder->children_count }}</dd></div>
                            <div class="media-details-row"><dt>បង្កើតដោយ</dt><dd>{{ $folder->creator?->name ?? '—' }}</dd></div>
                            <div class="media-details-row"><dt>កាលបរិច្ឆេទ</dt><dd>{{ $folder->created_at->format('d-m-Y') }}</dd></div>
                        </dl>
                        <a href="{{ route('admin.media.index', ['folder' => $folder->id]) }}" class="media-details-save-btn" style="display:block;text-align:center;text-decoration:none;">បើកថត</a>
                    </div>
                @endforeach
            </aside>
        </div>

        {{-- Preview modal (spec §12): large view with prev/next, cycling through every file
             currently listed on this page — same "click thumbnail or Preview" entry point for
             both the grid card and the list row. PDFs use the browser's own native PDF viewer
             (an <iframe> of the file URL); unsupported types just show file info + actions. --}}
        <div x-show="previewIndex !== null" x-cloak class="media-preview-overlay" @click.self="closePreview()" role="dialog" aria-modal="true">
            <button type="button" class="media-preview-close" @click="closePreview()" aria-label="បិទ">
                <x-news-icon name="close" width="20" height="20" />
            </button>
            <button type="button" class="media-preview-nav is-prev" @click="previewPrev()" aria-label="មុន" x-show="previewItems.length > 1">
                <x-news-icon name="chevron-left" width="22" height="22" />
            </button>
            <button type="button" class="media-preview-nav is-next" @click="previewNext()" aria-label="បន្ទាប់" x-show="previewItems.length > 1">
                <x-news-icon name="chevron-right" width="22" height="22" />
            </button>

            <div class="media-preview-body" @click.stop>
                <template x-if="previewItem && previewItem.type === 'image'">
                    <img :src="previewItem.url" :alt="previewItem.name" class="media-preview-media">
                </template>
                <template x-if="previewItem && previewItem.type === 'video'">
                    <video :src="previewItem.url" controls class="media-preview-media"></video>
                </template>
                <template x-if="previewItem && previewItem.type === 'pdf'">
                    <iframe :src="previewItem.url" class="media-preview-pdf"></iframe>
                </template>
                <template x-if="previewItem && !['image', 'video', 'pdf'].includes(previewItem.type)">
                    <div class="media-preview-fallback">
                        <x-media-file-icon type="file" :size="64" />
                        <p x-text="previewItem?.name"></p>
                        <a :href="previewItem?.url" download class="media-btn media-btn-primary" style="text-decoration:none;">ទាញយក</a>
                    </div>
                </template>

                <div class="media-preview-footer">
                    <p class="media-preview-name" x-text="previewItem?.name"></p>
                    <p class="media-preview-meta" x-text="[previewItem?.dims, previewItem?.size].filter(Boolean).join(' · ')"></p>
                </div>
            </div>
        </div>
    </div>

    @can('create', \App\Models\MediaFolder::class)
        <x-admin.modal name="create-folder" title="បង្កើតថតថ្មី">
            <form method="POST" action="{{ route('admin.media.folders.store') }}" class="flex flex-col gap-3">
                @csrf
                <input type="hidden" name="parent_id" value="{{ $currentFolder?->id }}">
                <x-admin.form-input name="name" label="ឈ្មោះថត" placeholder="ឧ. Hero, Partners, 2026" required />
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="open = false" class="text-muted text-[13px] font-bold px-3">បោះបង់</button>
                    <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-10 px-5">បង្កើត</button>
                </div>
            </form>
        </x-admin.modal>
    @endcan
</x-admin.layout>
