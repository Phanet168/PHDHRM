@php
    $blockTypes = [
        'heading' => 'ចំណងជើង', 'text' => 'អត្ថបទ', 'image' => 'រូបភាព', 'gallery' => 'វិចិត្រសាល',
        'button' => 'ប៊ូតុង', 'two_columns' => 'ពីរជួរឈរ', 'video' => 'វីដេអូ', 'quote' => 'សម្រង់',
        'html' => 'HTML', 'html_css' => 'HTML + CSS',
    ];
@endphp
<x-admin.layout :title="$content->title_km">
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <p class="font-bold text-brand-navy text-[18px]">{{ $content->title_km }}</p>
            <x-admin.status-badge :status="$content->status" />
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('content.show', $content) }}" target="_blank" class="bg-gray-100 text-ink font-bold text-[13px] rounded-[8px] h-9 px-4 flex items-center">មើលមុន (Preview)</a>
            @if ($content->status === 'draft')
                @can('submitReview', $content)
                    <x-admin.confirmation-dialog :action="route('admin.content.submit', $content)" message="ដាក់ស្នើសម្រាប់ការត្រួតពិនិត្យ?" label="ដាក់ស្នើត្រួតពិនិត្យ" class="bg-brand-blue text-white" />
                @endcan
            @endif
            @if ($content->status === 'pending_review')
                @can('approve', $content)
                    <x-admin.confirmation-dialog :action="route('admin.content.approve', $content)" message="អនុម័តអត្ថបទនេះ?" label="អនុម័ត" class="bg-brand-blue text-white" />
                @endcan
            @endif
            @if (in_array($content->status, ['approved', 'draft']))
                @can('publish', $content)
                    <x-admin.confirmation-dialog :action="route('admin.content.publish', $content)" message="ផ្សាយអត្ថបទនេះ?" label="ផ្សាយ" class="bg-brand-green text-white" />
                @endcan
            @endif
            @if ($content->status === 'published')
                @can('publish', $content)
                    <x-admin.confirmation-dialog :action="route('admin.content.archive', $content)" message="ទុកអត្ថបទនេះជាឯកសារ?" label="ទុកជាឯកសារ" class="bg-gray-200 text-ink" />
                @endcan
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('admin.content.update', $content) }}" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        @csrf @method('PUT')

        <div class="lg:col-span-2 flex flex-col gap-6">
            <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-admin.form-input name="title_km" label="ចំណងជើង (ខ្មែរ)" :value="$content->title_km" required />
                    <x-admin.form-input name="title_en" label="ចំណងជើង (អង់គ្លេស)" :value="$content->title_en" />
                    <x-admin.form-input name="slug" label="Slug" :value="$content->slug" required />
                </div>
                <x-admin.textarea name="excerpt" label="សេចក្តីសង្ខេប" :value="$content->excerpt" rows="3" />
                <x-admin.form-input name="tags" label="ស្លាកសញ្ញា (បំបែកដោយសញ្ញាក្បៀស ,)" :value="is_array($content->tags) ? implode(', ', $content->tags) : ''" placeholder="សុខភាពភ្នែក, បណ្តុះបណ្តាល, សុខភាពសាធារណៈ" />
            </div>

            <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4">
                <p class="font-bold text-brand-navy text-[15px]">SEO</p>
                <x-admin.form-input name="meta_title" label="Meta Title" :value="$content->meta_title" />
                <x-admin.textarea name="meta_description" label="Meta Description" :value="$content->meta_description" rows="2" />
            </div>
        </div>

        <div class="flex flex-col gap-6">
            <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4">
                <x-admin.select name="category_id" label="ប្រភេទមាតិកា" :value="$content->category_id" :options="$categories->pluck('name_km', 'id')" />

                <div>
                    <p class="text-ink text-[13px] font-bold mb-1">ស្ថានភាព</p>
                    <x-admin.status-badge :status="$content->status" />
                </div>

                <div>
                    <p class="text-ink text-[13px] font-bold mb-1">កាលបរិច្ឆេទផ្សព្វផ្សាយ</p>
                    <p class="text-muted text-[13px]">{{ $content->published_at?->format('d-m-Y H:i') ?? '— មិនទាន់ផ្សាយ —' }}</p>
                </div>

                <label class="flex items-center gap-2 text-[13px] text-ink">
                    <input type="checkbox" name="is_featured" value="1" @checked($content->is_featured) class="rounded border-line">
                    អត្ថបទលេចធ្លោ
                </label>

                <x-admin.upload-field name="featured_image" label="រូបភាពគម្រប" :current="$content->featured_image" accept="image/jpeg,image/png,image/webp" />
                <p class="text-muted text-[11px] -mt-2">ប្រសិនបើទុកទទេ នឹងប្រើរូបភាពដំបូងក្នុងវិចិត្រសាល ឬរូបភាពលំនាំដើម។</p>

                <div>
                    <p class="text-ink text-[13px] font-bold mb-1">អ្នកនិពន្ធ</p>
                    <p class="text-muted text-[13px]">{{ $content->author?->name ?? '—' }}</p>
                </div>

                <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6 w-full">រក្សាទុក (Save Draft)</button>
            </div>
        </div>
    </form>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
        <div class="lg:col-span-2 flex flex-col gap-6">

            {{-- Content Block Builder --}}
            <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6" x-data="{ adding: null, editing: null }">
                <p class="font-bold text-brand-navy text-[15px] mb-1">សំណង់មាតិកា (Content Blocks)</p>
                <p class="text-muted text-[12px] mb-4">អូសរូបតំណាង ⠿ ដើម្បីតម្រៀបលំដាប់ Block។</p>

                <div data-block-sort-root data-reorder-url="{{ route('admin.content.blocks.reorder', $content) }}">
                    <ul class="content-block-list flex flex-col gap-2">
                        @forelse ($content->blocks as $block)
                            <li class="block-item border border-line rounded-[8px]" data-id="{{ $block->id }}">
                                <div class="flex items-center gap-3 px-3 py-[10px]">
                                    <span class="block-drag-handle cursor-move text-muted select-none">⠿</span>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="bg-brand-blue-tint text-brand-blue text-[10px] font-bold rounded-full px-2 py-[2px]">{{ $blockTypes[$block->block_type] ?? $block->block_type }}</span>
                                            @if (! $block->is_active)
                                                <span class="bg-gray-100 text-gray-500 text-[10px] font-bold rounded-full px-2 py-[2px]">បិទ</span>
                                            @endif
                                        </div>
                                        <p class="text-ink text-[13px] truncate mt-1">{{ \Illuminate\Support\Str::limit(strip_tags($block->get('text') ?? $block->get('html') ?? $block->get('left_html') ?? $block->get('caption') ?? ''), 80) ?: '—' }}</p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0 text-[12px] font-bold">
                                        <button type="button" @click="editing = (editing === {{ $block->id }} ? null : {{ $block->id }})" class="text-brand-blue">កែប្រែ</button>
                                        <form method="POST" action="{{ route('admin.content.blocks.duplicate', [$content, $block]) }}">
                                            @csrf
                                            <button type="submit" class="text-muted">ចម្លង</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.content.blocks.toggle', [$content, $block]) }}">
                                            @csrf
                                            <button type="submit" class="{{ $block->is_active ? 'text-muted' : 'text-brand-green' }}">{{ $block->is_active ? 'បិទ' : 'បើក' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.content.blocks.destroy', [$content, $block]) }}" onsubmit="return confirm('លុប Block នេះ?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-brand-red">លុប</button>
                                        </form>
                                    </div>
                                </div>
                                <div x-show="editing === {{ $block->id }}" x-cloak class="border-t border-line p-4">
                                    <form method="POST" action="{{ route('admin.content.blocks.update', [$content, $block]) }}" enctype="multipart/form-data" class="flex flex-col gap-3">
                                        @csrf @method('PUT')
                                        @include('admin.content._block-fields', ['type' => $block->block_type, 'values' => $block->data, 'content' => $content])
                                        <div class="flex justify-end">
                                            <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-9 px-4">រក្សាទុក Block</button>
                                        </div>
                                    </form>
                                </div>
                            </li>
                        @empty
                            <li class="text-muted text-[13px] py-6 text-center list-none">មិនទាន់មាន Block ទេ។</li>
                        @endforelse
                    </ul>
                </div>

                <div class="border-t border-line mt-4 pt-4">
                    <p class="text-ink text-[13px] font-bold mb-2">បន្ថែម Block ថ្មី</p>
                    <div class="flex flex-wrap gap-2 mb-3">
                        @foreach ($blockTypes as $type => $label)
                            @if ($type !== 'html_css' || auth()->user()->can('content.custom_html'))
                                <button type="button" @click="adding = (adding === '{{ $type }}' ? null : '{{ $type }}')"
                                        class="border border-line rounded-full px-3 py-1 text-[12px] font-bold" :class="adding === '{{ $type }}' ? 'bg-brand-blue text-white border-brand-blue' : 'text-ink'">
                                    + {{ $label }}
                                </button>
                            @endif
                        @endforeach
                    </div>

                    @foreach ($blockTypes as $type => $label)
                        <div x-show="adding === '{{ $type }}'" x-cloak class="border border-line rounded-[8px] p-4 mb-2">
                            <form method="POST" action="{{ route('admin.content.blocks.store', $content) }}" enctype="multipart/form-data" class="flex flex-col gap-3">
                                @csrf
                                <input type="hidden" name="block_type" value="{{ $type }}">
                                @include('admin.content._block-fields', ['type' => $type, 'values' => [], 'content' => $content])
                                <div class="flex justify-end">
                                    <button type="submit" class="bg-brand-green text-white font-bold text-[13px] rounded-[8px] h-9 px-4">បន្ថែម Block</button>
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Gallery --}}
            <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6">
                <p class="font-bold text-brand-navy text-[15px] mb-1">វិចិត្រសាល (Gallery)</p>
                <p class="text-muted text-[12px] mb-4">អូសរូបភាពដើម្បីតម្រៀបលំដាប់។ ចុច ★ ដើម្បីកំណត់ជារូបភាពគម្របលំនាំដើម (ប្រសិនបើមិនបានកំណត់រូបគម្របផ្ទាល់)។</p>

                <div data-gallery-sort-root data-reorder-url="{{ route('admin.content.images.reorder', $content) }}">
                    <div class="content-gallery-list grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                        @forelse ($content->images as $image)
                            <div class="gallery-item relative group border border-line rounded-[8px] overflow-hidden" data-id="{{ $image->id }}">
                                <span class="gallery-drag-handle absolute top-1 left-1 bg-white/80 rounded px-1 text-[11px] cursor-move z-10">⠿</span>
                                <img src="{{ $image->url() }}" class="h-[100px] w-full object-cover">
                                @if ($image->is_featured)
                                    <span class="absolute top-1 right-1 text-amber-400 text-[14px]">★</span>
                                @endif
                                <div class="p-2 flex flex-col gap-1">
                                    <form method="POST" action="{{ route('admin.content.images.update', [$content, $image]) }}" class="flex flex-col gap-1">
                                        @csrf @method('PUT')
                                        <input type="text" name="caption" value="{{ $image->caption }}" placeholder="ចំណងជើង" class="text-[11px] border border-line rounded px-2 py-1 w-full">
                                        <input type="text" name="alt_text" value="{{ $image->alt_text }}" placeholder="Alt text" class="text-[11px] border border-line rounded px-2 py-1 w-full">
                                        <button type="submit" class="text-brand-blue text-[11px] font-bold text-left">រក្សាទុក</button>
                                    </form>
                                    <div class="flex items-center justify-between">
                                        <form method="POST" action="{{ route('admin.content.images.feature', [$content, $image]) }}">
                                            @csrf
                                            <button type="submit" class="text-amber-500 text-[11px] font-bold">កំណត់ជាគម្រប</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.content.images.destroy', [$content, $image]) }}" onsubmit="return confirm('លុបរូបភាពនេះ?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-brand-red text-[11px] font-bold">លុប</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted text-[13px] col-span-4">មិនទាន់មានរូបភាពទេ។</p>
                        @endforelse
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.content.images.store', $content) }}" enctype="multipart/form-data" class="flex flex-wrap gap-2 items-end border-t border-line pt-4">
                    @csrf
                    <div class="flex-1 min-w-[220px]">
                        <label class="block text-ink text-[13px] font-bold mb-1">បន្ថែមរូបភាព (អាចជ្រើសរើសច្រើន)</label>
                        <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp"
                               class="block w-full text-[13px] text-muted file:mr-3 file:py-2 file:px-4 file:rounded-[8px] file:border-0 file:bg-brand-blue-tint file:text-brand-blue file:font-bold file:text-[13px]">
                    </div>
                    <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-11 px-5">បញ្ចូល</button>
                </form>

                <details class="mt-4 border-t border-line pt-4">
                    <summary class="text-brand-blue text-[13px] font-bold cursor-pointer">ជ្រើសរើសពី Media Library ដើម្បីជៀសវាងឯកសារស្ទួន</summary>
                    <form method="POST" action="{{ route('admin.content.images.from-media', $content) }}" class="mt-3">
                        @csrf
                        <div class="grid grid-cols-3 md:grid-cols-6 gap-2 max-h-[240px] overflow-y-auto mb-3">
                            @foreach (\App\Models\Media::where('mime_type', 'like', 'image/%')->latest()->take(48)->get() as $media)
                                <label class="relative">
                                    <input type="checkbox" name="media_ids[]" value="{{ $media->id }}" class="absolute top-1 left-1 rounded">
                                    <img src="{{ $media->url }}" title="{{ $media->original_filename }}" class="h-[70px] w-full object-cover rounded-[6px] border border-line">
                                </label>
                            @endforeach
                        </div>
                        <button type="submit" class="bg-brand-blue text-white font-bold text-[13px] rounded-[8px] h-9 px-4">បន្ថែមដែលបានជ្រើសរើស</button>
                    </form>
                </details>
            </div>
        </div>

        <div></div>
    </div>
</x-admin.layout>
