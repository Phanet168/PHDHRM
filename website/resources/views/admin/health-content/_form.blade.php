@php $content = $content ?? null; @endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <x-admin.form-input name="title_km" label="ចំណងជើង (ខ្មែរ)" :value="$content?->title_km" required />
    <x-admin.form-input name="title_en" label="ចំណងជើង (អង់គ្លេស)" :value="$content?->title_en" />
    <x-admin.form-input name="slug" label="Slug" :value="$content?->slug" required />
    <x-admin.select name="category_id" label="ប្រភេទ" :value="$content?->category_id" :options="$categories->pluck('name_km', 'id')" />
</div>

<x-admin.textarea name="excerpt" label="សេចក្តីសង្ខេប" :value="$content?->excerpt" rows="2" />
<x-admin.textarea name="content" label="ខ្លឹមសារ" :value="$content?->content" rows="8" />
<x-admin.textarea name="educational_message" label="សារអប់រំសុខភាព (សង្ខេប ២-៤ ប្រយោគ)" :value="$content?->educational_message" rows="3" />

<div class="border-t border-line pt-4 flex flex-col gap-3">
    <label class="flex items-center gap-2 text-[13px] font-bold text-ink">
        <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $content?->is_featured))>
        ជាមាតិកាលេចធ្លោលើទំព័រដើម (Featured)
    </label>

    <div>
        <p class="text-ink text-[13px] font-bold mb-1">រូបភាពគម្រប / Thumbnail</p>
        @include('admin.pages._media-picker', [
            'name' => 'thumbnail_media_id',
            'selected' => $content?->thumbnail_media_id,
            'defaultFolderId' => $contentFolderId ?? null,
        ])
    </div>

    <div x-data="{ videoType: '{{ old('video_type', $content?->video_type ?? '') }}' }" class="flex flex-col gap-3">
        <x-admin.select name="video_type" label="ប្រភេទវីដេអូ" :value="$content?->video_type" x-model="videoType" :options="['youtube' => 'YouTube (តំណភ្ជាប់)', 'uploaded' => 'ឯកសារវីដេអូ (Media Library)']" placeholder="គ្មានវីដេអូ" />

        <div x-show="videoType === 'youtube'" x-cloak>
            <x-admin.form-input name="video_url" label="តំណភ្ជាប់វីដេអូ (YouTube)" :value="$content?->video_url" placeholder="https://www.youtube.com/watch?v=..." />
        </div>

        <div x-show="videoType === 'uploaded'" x-cloak class="flex flex-col gap-2">
            <x-admin.upload-field name="video_file" label="Upload ឯកសារវីដេអូថ្មី (mp4/webm)" accept="video/mp4,video/webm" />
            @include('admin.pages._media-picker', [
                'name' => 'video_media_id',
                'selected' => $content?->video_media_id,
                'defaultFolderId' => $contentFolderId ?? null,
                'mimeType' => 'video/%',
            ])
        </div>
    </div>

    <x-admin.form-input name="duration" label="រយៈពេលវីដេអូ (មម:វិ, ឧ. 06:25)" :value="$content?->durationLabel()" placeholder="06:25" />
</div>

@if ($content?->youtube_video_id)
    <div class="border-t border-line pt-4 mt-4 flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <p class="text-ink text-[13px] font-bold">ព័ត៌មានប្រភព (នាំចូលពី YouTube)</p>
            @if ($content->summary_status === 'needs_review')
                <span class="bg-brand-red/10 text-brand-red text-[11px] font-bold rounded-full px-3 py-1">ត្រូវការពិនិត្យ</span>
            @else
                <span class="bg-brand-green-tint text-brand-green text-[11px] font-bold rounded-full px-3 py-1">បានពិនិត្យ</span>
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-[13px]">
            <div>
                <p class="text-muted text-[11px] font-bold mb-1">ចំណងជើងដើម</p>
                <p class="text-ink">{{ $content->title_original }}</p>
            </div>
            <div>
                <p class="text-muted text-[11px] font-bold mb-1">ប្រភព</p>
                <p class="text-ink">
                    {{ $content->source_name }}
                    @if ($content->source_channel_url)
                        · <a href="{{ $content->source_channel_url }}" target="_blank" rel="noopener" class="text-brand-blue">Channel</a>
                    @endif
                    @if ($content->source_video_url)
                        · <a href="{{ $content->source_video_url }}" target="_blank" rel="noopener" class="text-brand-blue">មើលនៅ YouTube</a>
                    @endif
                </p>
            </div>
            @if ($content->source_published_at)
                <div>
                    <p class="text-muted text-[11px] font-bold mb-1">កាលបរិច្ឆេទផ្សាយដើម</p>
                    <p class="text-ink">{{ $content->source_published_at->format('d-m-Y') }}</p>
                </div>
            @endif
        </div>

        @if ($content->source_description)
            <div>
                <p class="text-muted text-[11px] font-bold mb-1">ការពិពណ៌នាដើមពី YouTube (សម្រាប់យោង — មិនត្រូវបានផ្សាយ)</p>
                <p class="text-ink text-[12px] bg-surface rounded-[8px] p-3 max-h-[140px] overflow-y-auto whitespace-pre-line">{{ $content->source_description }}</p>
            </div>
        @endif

        <div class="flex gap-2">
            <x-admin.confirmation-dialog :action="route('admin.health-content.refresh-metadata', $content)" message="ធ្វើបច្ចុប្បន្នភាព Metadata ពី YouTube?" label="ធ្វើបច្ចុប្បន្នភាព Metadata" class="bg-gray-200 text-ink" />
            <x-admin.confirmation-dialog :action="route('admin.health-content.save-thumbnail', $content)" message="រក្សារូបតំណាងក្នុង Media Library?" label="រក្សារូបតំណាងក្នុង Media Library" class="bg-gray-200 text-ink" />
        </div>
        @error('metadata')
            <p class="text-brand-red text-[12px]">{{ $message }}</p>
        @enderror
        @error('thumbnail')
            <p class="text-brand-red text-[12px]">{{ $message }}</p>
        @enderror
    </div>
@endif
