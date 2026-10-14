@php
    $blockTypes = [
        'heading' => 'ចំណងជើង', 'text' => 'អត្ថបទ', 'image' => 'រូបភាព', 'image_text' => 'រូបភាព+អត្ថបទ',
        'gallery' => 'វិចិត្រសាល', 'button' => 'ប៊ូតុង', 'two_columns' => 'ពីរជួរឈរ', 'cards' => 'កាត',
        'video' => 'វីដេអូ', 'quote' => 'សម្រង់', 'statistics' => 'ស្ថិតិ', 'contact_info' => 'ទំនាក់ទំនង',
        'map' => 'ផែនទី', 'html_css' => 'HTML + CSS',
    ];
    $templates = [
        'default' => 'លំនាំដើម', 'about' => 'អំពី', 'contact' => 'ទំនាក់ទំនង',
        'leadership' => 'ថ្នាក់ដឹកនាំ', 'full_width' => 'ពេញទទឹង', 'landing' => 'ចុះចត',
    ];
    $latestReview = $page->reviewLogs->first();
@endphp
<x-admin.layout :title="$page->title_km">
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <p class="font-bold text-brand-navy text-[18px]">{{ $page->title_km }}</p>
            <x-admin.status-badge :status="$page->status" />
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('admin.pages.preview', $page) }}" target="_blank" class="bg-gray-100 text-ink font-bold text-[13px] rounded-[8px] h-9 px-4 flex items-center">មើលសាកល្បង (Preview)</a>

            @if ($page->status === 'draft')
                @can('submitReview', $page)
                    <x-admin.confirmation-dialog :action="route('admin.pages.submit', $page)" message="ដាក់ស្នើសម្រាប់ការត្រួតពិនិត្យ?" label="ដាក់ស្នើត្រួតពិនិត្យ" class="bg-brand-blue text-white" />
                @endcan
            @endif

            @if ($page->status === 'pending_review')
                @can('approve', $page)
                    <x-admin.confirmation-dialog :action="route('admin.pages.approve', $page)" message="អនុម័តទំព័រនេះ?" label="អនុម័ត" class="bg-brand-blue text-white" />
                @endcan
                @can('review', $page)
                    <button type="button" @click="rejecting = ! rejecting" class="bg-gray-200 text-ink font-bold text-[13px] rounded-[8px] h-9 px-4">ច្រានចោល</button>
                @endcan
            @endif

            @if (in_array($page->status, ['approved', 'draft']))
                @can('publish', $page)
                    <x-admin.confirmation-dialog :action="route('admin.pages.publish', $page)" message="ផ្សាយទំព័រនេះ?" label="ផ្សាយ" class="bg-brand-green text-white" />
                @endcan
            @endif

            @if ($page->status === 'published')
                @can('unpublish', $page)
                    <x-admin.confirmation-dialog :action="route('admin.pages.unpublish', $page)" message="ដកទំព័រនេះពីការផ្សាយ?" label="ដកពីការផ្សាយ" class="bg-gray-200 text-ink" />
                @endcan
            @endif

            @if (! in_array($page->status, ['archived']))
                @can('unpublish', $page)
                    <x-admin.confirmation-dialog :action="route('admin.pages.archive', $page)" message="ទុកទំព័រនេះជាឯកសារ?" label="ទុកជាឯកសារ" class="bg-gray-200 text-ink" />
                @endcan
            @endif
        </div>
    </div>

    <div x-data="{ rejecting: false }">
        @if ($page->status === 'pending_review')
            <div x-show="rejecting" x-cloak class="bg-white border border-line rounded-[10px] p-4 mb-5">
                <form method="POST" action="{{ route('admin.pages.reject', $page) }}" class="flex flex-col gap-2">
                    @csrf
                    <x-admin.textarea name="note" label="មូលហេតុច្រានចោល (នឹងបង្ហាញដល់អ្នកនិពន្ធ)" rows="2" required />
                    <div class="flex justify-end">
                        <button type="submit" class="bg-brand-red text-white font-bold text-[13px] rounded-[8px] h-9 px-4">ច្រានចោលទំព័រ</button>
                    </div>
                </form>
            </div>
        @endif

        @if ($latestReview && $latestReview->note)
            <div class="bg-amber-50 border border-amber-200 rounded-[10px] p-4 mb-5 text-[13px] text-amber-800">
                <p class="font-bold">មតិចុងក្រោយ ({{ \App\Models\PageReviewLog::actionLabel($latestReview->action) }}) — {{ $latestReview->reviewer?->name ?? '—' }}, {{ $latestReview->created_at->format('d-m-Y H:i') }}</p>
                <p class="mt-1">{{ $latestReview->note }}</p>
            </div>
        @endif
    </div>

    <form method="POST" action="{{ route('admin.pages.update', $page) }}" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        @csrf @method('PUT')

        <div class="lg:col-span-2 flex flex-col gap-6">
            <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-admin.form-input name="title_km" label="ចំណងជើង (ខ្មែរ)" :value="$page->title_km" required />
                    <x-admin.form-input name="title_en" label="ចំណងជើង (អង់គ្លេស)" :value="$page->title_en" />
                    <x-admin.form-input name="slug" label="Slug" :value="$page->slug" required />
                    <x-admin.select name="template" label="Template" :value="$page->template" :placeholder="null" :options="$templates" />
                </div>
                <x-admin.textarea name="excerpt" label="សេចក្តីសង្ខេប" :value="$page->excerpt" rows="3" />
            </div>

            <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4">
                <p class="font-bold text-brand-navy text-[15px]">SEO</p>
                <x-admin.form-input name="meta_title" label="Meta Title (ទុកទទេ = ប្រើចំណងជើងទំព័រ)" :value="$page->meta_title" />
                <x-admin.textarea name="meta_description" label="Meta Description (ទុកទទេ = ប្រើសេចក្តីសង្ខេប)" :value="$page->meta_description" rows="2" />
                <x-admin.form-input name="canonical_url" label="Canonical URL (ស្រេចចិត្ត)" :value="$page->canonical_url" />
            </div>

            <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6 self-start">រក្សាទុកព័ត៌មានទំព័រ</button>
        </div>

        <div class="flex flex-col gap-6">
            <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 flex flex-col gap-4">
                <div>
                    <p class="text-ink text-[13px] font-bold mb-1">ស្ថានភាព</p>
                    <x-admin.status-badge :status="$page->status" />
                </div>
                <div>
                    <p class="text-ink text-[13px] font-bold mb-1">កាលបរិច្ឆេទផ្សព្វផ្សាយ</p>
                    <p class="text-muted text-[13px]">{{ $page->published_at?->format('d-m-Y H:i') ?? '— មិនទាន់ផ្សាយ —' }}</p>
                </div>
                <div>
                    <p class="text-ink text-[13px] font-bold mb-1">អ្នកនិពន្ធ / អ្នកត្រួតពិនិត្យ</p>
                    <p class="text-muted text-[13px]">{{ $page->author?->name ?? '—' }} / {{ $page->reviewer?->name ?? '—' }}</p>
                </div>

                <div class="border-t border-line pt-3">
                    <p class="text-ink text-[13px] font-bold mb-2">រូបភាពគម្រប (Featured Image)</p>
                    @if ($page->featuredMedia)
                        <img src="{{ $page->featuredMedia->url }}" class="w-full h-[120px] object-cover rounded-[8px] mb-2">
                    @endif
                    <x-admin.upload-field name="featured_image" label="ជំនួសរូបភាព" accept="image/jpeg,image/png,image/webp" />
                    @include('admin.pages._media-picker', [
                        'name' => 'featured_media_id',
                        'selected' => $page->featured_media_id,
                        'defaultFolderId' => $pageFolderId,
                    ])
                    @if ($page->featuredMedia)
                        <label class="flex items-center gap-2 text-[12px] text-brand-red mt-2">
                            <input type="checkbox" name="remove_featured_image" value="1">
                            យកចេញរូបភាពគម្រប
                        </label>
                    @endif
                </div>

                @can('manageMenu', $page)
                    <div class="border-t border-line pt-3 flex flex-col gap-3">
                        <p class="text-ink text-[13px] font-bold">ការដាក់បញ្ចូលក្នុងម៉ឺនុយ</p>

                        @foreach (['header' => 'ម៉ឺនុយក្បាល', 'footer' => 'ម៉ឺនុយបាត'] as $slug => $menuLabel)
                            @php $existingItem = ${$slug . 'Item'}; @endphp
                            <div x-data="{ enabled: {{ $existingItem ? 'true' : 'false' }} }" class="border border-line rounded-[8px] p-3">
                                <label class="flex items-center gap-2 text-[13px] font-bold text-ink">
                                    <input type="checkbox" name="menu_{{ $slug }}_enabled" value="1" x-model="enabled" @checked($existingItem)>
                                    {{ $menuLabel }}
                                </label>
                                <div x-show="enabled" x-cloak class="flex flex-col gap-2 mt-2">
                                    <input type="text" name="menu_{{ $slug }}_label" value="{{ $existingItem?->label_km }}" placeholder="ស្លាកម៉ឺនុយ (ទុកទទេ = ចំណងជើងទំព័រ)" class="text-[12px] border border-line rounded px-2 py-1 w-full">
                                    <select name="menu_{{ $slug }}_parent_id" class="text-[12px] border border-line rounded px-2 py-1 w-full">
                                        <option value="">— គ្មានម៉ែ —</option>
                                        @foreach (${$slug . 'Parents'} as $parent)
                                            <option value="{{ $parent->id }}" @selected($existingItem?->parent_id === $parent->id)>{{ $parent->label_km }}</option>
                                        @endforeach
                                    </select>
                                    <div class="flex gap-2">
                                        <input type="number" name="menu_{{ $slug }}_sort_order" value="{{ $existingItem?->sort_order ?? 0 }}" placeholder="លំដាប់" class="text-[12px] border border-line rounded px-2 py-1 w-1/2">
                                        <select name="menu_{{ $slug }}_target" class="text-[12px] border border-line rounded px-2 py-1 w-1/2">
                                            <option value="_self" @selected(($existingItem?->target ?? '_self') === '_self')>ទំព័រដដែល</option>
                                            <option value="_blank" @selected($existingItem?->target === '_blank')>ផ្ទាំងថ្មី</option>
                                        </select>
                                    </div>
                                    @if ($existingItem)
                                        <label class="flex items-center gap-2 text-[11px] text-muted">
                                            <input type="checkbox" name="menu_{{ $slug }}_sync_label" value="1">
                                            ធ្វើសមកាលកម្មស្លាកជាមួយចំណងជើងទំព័រ
                                        </label>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        <p class="text-muted text-[11px]">ការកំណត់ម៉ឺនុយនឹងរក្សាទុកនៅពេលចុច "រក្សាទុកព័ត៌មានទំព័រ" ខាងលើ។</p>
                    </div>
                @endcan
            </div>
        </div>
    </form>

    {{-- Page Block Builder --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
        <div class="lg:col-span-2 flex flex-col gap-6">
            <div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6" x-data="{ adding: null, editing: null }">
                <p class="font-bold text-brand-navy text-[15px] mb-1">សំណង់ទំព័រ (Page Blocks)</p>
                <p class="text-muted text-[12px] mb-4">អូសរូបតំណាង ⠿ ដើម្បីតម្រៀបលំដាប់ Block។</p>

                <div data-block-sort-root data-reorder-url="{{ route('admin.pages.blocks.reorder', $page) }}">
                    <ul class="content-block-list flex flex-col gap-2">
                        @forelse ($page->blocks as $block)
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
                                        <p class="text-ink text-[13px] truncate mt-1">{{ \Illuminate\Support\Str::limit(strip_tags($block->get('text') ?? $block->get('html') ?? $block->get('heading') ?? $block->get('left_html') ?? $block->get('caption') ?? $block->get('address') ?? ''), 80) ?: '—' }}</p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0 text-[12px] font-bold">
                                        <button type="button" @click="editing = (editing === {{ $block->id }} ? null : {{ $block->id }})" class="text-brand-blue">កែប្រែ</button>
                                        <form method="POST" action="{{ route('admin.pages.blocks.duplicate', [$page, $block]) }}">
                                            @csrf
                                            <button type="submit" class="text-muted">ចម្លង</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.pages.blocks.toggle', [$page, $block]) }}">
                                            @csrf
                                            <button type="submit" class="{{ $block->is_active ? 'text-muted' : 'text-brand-green' }}">{{ $block->is_active ? 'បិទ' : 'បើក' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.pages.blocks.destroy', [$page, $block]) }}" onsubmit="return confirm('លុប Block នេះ?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-brand-red">លុប</button>
                                        </form>
                                    </div>
                                </div>
                                <div x-show="editing === {{ $block->id }}" x-cloak class="border-t border-line p-4">
                                    <form method="POST" action="{{ route('admin.pages.blocks.update', [$page, $block]) }}" enctype="multipart/form-data" class="flex flex-col gap-3">
                                        @csrf @method('PUT')
                                        @include('admin.pages._block-fields', ['type' => $block->block_type, 'values' => $block->data])
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
                            @if ($type !== 'html_css' || auth()->user()->can('page.custom_html'))
                                <button type="button" @click="adding = (adding === '{{ $type }}' ? null : '{{ $type }}')"
                                        class="border border-line rounded-full px-3 py-1 text-[12px] font-bold" :class="adding === '{{ $type }}' ? 'bg-brand-blue text-white border-brand-blue' : 'text-ink'">
                                    + {{ $label }}
                                </button>
                            @endif
                        @endforeach
                    </div>

                    @foreach ($blockTypes as $type => $label)
                        <div x-show="adding === '{{ $type }}'" x-cloak class="border border-line rounded-[8px] p-4 mb-2">
                            <form method="POST" action="{{ route('admin.pages.blocks.store', $page) }}" enctype="multipart/form-data" class="flex flex-col gap-3">
                                @csrf
                                <input type="hidden" name="block_type" value="{{ $type }}">
                                @include('admin.pages._block-fields', ['type' => $type, 'values' => []])
                                <div class="flex justify-end">
                                    <button type="submit" class="bg-brand-green text-white font-bold text-[13px] rounded-[8px] h-9 px-4">បន្ថែម Block</button>
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div></div>
    </div>
</x-admin.layout>
