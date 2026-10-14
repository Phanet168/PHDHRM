<div x-show="isDetailsFor('media', {{ $item->id }})" x-cloak>
    <div class="media-details-preview">
        @if ($item->isImage() && ! $item->fileMissing())
            <img src="{{ $item->url }}" alt="{{ $item->alt_text ?? $item->original_filename }}">
        @elseif ($item->isVideo() && ! $item->fileMissing())
            <video src="{{ $item->url }}" controls preload="metadata"></video>
        @else
            <x-media-file-icon :type="$item->iconType()" :size="64" />
        @endif
    </div>

    @if ($item->fileMissing())
        <div class="media-reference-warning">
            <p class="font-bold">រកមិនឃើញឯកសារនេះ (Missing file)</p>
            <p>ឯកសារនេះនៅមានក្នុងទិន្នន័យ ប៉ុន្តែឯកសារពិតនៅលើឌីសបាត់ ឬត្រូវបានលុបចេញក្រៅប្រព័ន្ធ។ សូមផ្លាស់ប្តូរឯកសារថ្មី ឬលុបកំណត់ត្រានេះទៅធុងសម្រាម។</p>
        </div>
    @endif

    <p class="media-details-name">{{ $item->original_filename }}</p>

    <dl>
        <div class="media-details-row"><dt>ទំហំ</dt><dd>{{ $item->formattedSize() }}</dd></div>
        @if ($item->dimensions())
            <div class="media-details-row"><dt>វិមាត្រ</dt><dd>{{ $item->dimensions() }}</dd></div>
        @endif
        <div class="media-details-row"><dt>ប្រភេទ</dt><dd>{{ $item->mime_type }}</dd></div>
        <div class="media-details-row"><dt>ថត</dt><dd>{{ $item->folder?->name ?? 'Media' }}</dd></div>
        <div class="media-details-row"><dt>បញ្ចូលនៅ</dt><dd>{{ $item->created_at->format('d M Y H:i') }}</dd></div>
    </dl>

    <div class="media-details-uploader">
        <div class="media-details-avatar">{{ mb_substr($item->uploader?->name ?? '?', 0, 1) }}</div>
        <span class="text-[13px] font-bold text-ink">{{ $item->uploader?->name ?? '—' }}</span>
    </div>

    <div class="media-details-url-row">
        <input type="text" readonly value="{{ $item->url }}" onclick="this.select()">
        <button type="button" class="media-details-copy-btn" @click="copyUrl('{{ $item->url }}')">ចម្លង</button>
    </div>

    {{-- ប្រើនៅក្នុង / Used In — fetched on-demand for whichever single file is selected
         (mediaExplorer.loadUsage(), see media-explorer.js), not eagerly for every card. --}}
    <div class="media-details-usage" x-show="usageForKey === 'media:{{ $item->id }}'" x-cloak>
        <p class="media-details-usage-title">ប្រើនៅក្នុង / Used In</p>
        <p x-show="usageLoading" class="media-details-usage-empty">កំពុងពិនិត្យ...</p>
        <template x-if="!usageLoading && usageReferences && usageReferences.length === 0">
            <p class="media-details-usage-empty">មិនទាន់ប្រើនៅកន្លែងណាទេ — អាចលុបបានដោយសុវត្ថិភាព។</p>
        </template>
        <template x-if="!usageLoading && usageReferences && usageReferences.length > 0">
            <ul class="media-details-usage-list">
                <template x-for="ref in usageReferences" :key="ref.label">
                    <li>
                        <span x-text="ref.label + ' (' + ref.count + ')'"></span>
                        <template x-for="link in ref.links" :key="link.url || link.title">
                            <a :href="link.url" x-show="link.url" target="_blank" x-text="link.title"></a>
                        </template>
                    </li>
                </template>
            </ul>
        </template>
    </div>

    @can('update', $item)
        <form method="POST" action="{{ route('admin.media.update', $item) }}">
            @csrf @method('PUT')
            <div class="media-details-field">
                <label for="alt-{{ $item->id }}">Alt Text</label>
                <input type="text" id="alt-{{ $item->id }}" name="alt_text" value="{{ $item->alt_text }}">
            </div>
            <div class="media-details-field">
                <label for="caption-{{ $item->id }}">Caption</label>
                <textarea id="caption-{{ $item->id }}" name="caption">{{ $item->caption }}</textarea>
            </div>
            <button type="submit" class="media-details-save-btn">កែសម្រួលព័ត៌មាន</button>
        </form>
    @endcan
</div>
