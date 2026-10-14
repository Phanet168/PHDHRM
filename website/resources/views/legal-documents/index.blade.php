@use('App\Support\KhmerDate')
@php
    $currentType = (string) request('type', '');
    $activeCategory = $categories->firstWhere('id', $currentType);
    $searchFilters = request()->only(['year', 'issuing_body', 'keyword']);
    $hasFilters = $currentType !== '' || collect($searchFilters)->filter(fn ($value) => filled($value))->isNotEmpty();
    // Each category gets a stable soft tint for its badge (position in the ordered category list).
    $categoryTone = $categories->pluck('id')->values()->flip();
    $tileLabels = ['pdf' => 'PDF', 'word' => 'DOC', 'excel' => 'XLS'];
@endphp
<x-layout title="លិខិតបទដ្ឋានគតិយុត្ត">
    <x-page-banner page="legal-documents" title="លិខិតបទដ្ឋានគតិយុត្ត" />
    <x-page-header title="លិខិតបទដ្ឋានគតិយុត្ត" />

    <main class="legal-page public-container">
        <div class="legal-titlebar">
            <span class="legal-titlebar-icon"><x-news-icon name="document" width="22" height="22" /></span>
            <h2>លិខិតបទដ្ឋានគតិយុត្ត</h2>
        </div>

        <section class="legal-search-card" aria-label="ស្វែងរកឯកសារ">
            <form method="GET" action="{{ route('legal-documents.index') }}" class="legal-search-form">
                <label for="legal-type" class="legal-field-type"><span class="legal-label"><x-news-icon name="document" width="17" height="17" />ប្រភេទឯកសារ</span>
                    <select id="legal-type" name="type">
                        <option value="">គ្រប់ប្រភេទ</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($currentType === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label for="legal-issuing-body" class="legal-field-source"><span class="legal-label"><x-news-icon name="hospital" width="17" height="17" />ប្រភពចេញផ្សាយ / អង្គភាព</span>
                    <select id="legal-issuing-body" name="issuing_body">
                        <option value="">គ្រប់ស្ថាប័ន</option>
                        @foreach ($issuingBodies as $body)
                            <option value="{{ $body }}" @selected(request('issuing_body') === $body)>{{ $body }}</option>
                        @endforeach
                    </select>
                </label>
                <label for="legal-year" class="legal-field-year"><span class="legal-label"><x-news-icon name="calendar" width="17" height="17" />ឆ្នាំ</span>
                    <select id="legal-year" name="year">
                        <option value="">គ្រប់ឆ្នាំ</option>
                        @foreach ($years as $year)
                            <option value="{{ $year }}" @selected((string) request('year') === (string) $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </label>
                <label for="legal-keyword" class="legal-field-keyword"><span class="legal-label"><x-news-icon name="search" width="17" height="17" />ពាក្យគន្លឹះ</span>
                    <input id="legal-keyword" name="keyword" value="{{ request('keyword') }}" placeholder="ស្វែងរកតាមចំណងជើង លេខឯកសារ..." maxlength="255">
                </label>
                <div class="legal-search-actions">
                    <button type="submit"><x-news-icon name="search" width="18" height="18" />ស្វែងរក</button>
                    <a href="{{ route('legal-documents.index') }}" aria-label="សម្អាតការស្វែងរក"><x-news-icon name="refresh" width="18" height="18" />សម្អាត</a>
                </div>
            </form>
        </section>

        <div class="legal-layout">
            <div class="legal-main">
                <section id="legal-results" class="legal-results" aria-labelledby="legal-results-heading">
                    <div class="legal-results-heading">
                        <h2 id="legal-results-heading">
                            លទ្ធផលស្វែងរក
                            <span class="legal-results-count">({{ KhmerDate::toKhmerDigits((string) $documents->total()) }} ឯកសារ)</span>
                        </h2>
                        @if ($activeCategory)
                            <p class="legal-results-scope">ប្រភេទ៖ {{ $activeCategory->name }}</p>
                        @endif
                        <p class="legal-results-sort">តម្រៀបតាម៖ <strong>កាលបរិច្ឆេទថ្មីបំផុត</strong></p>
                    </div>

                    @if ($documents->isEmpty())
                        <div class="legal-empty">
                            <x-news-icon name="search" width="40" height="40" />
                            <p>មិនមានឯកសារដែលត្រូវនឹងការស្វែងរកទេ</p>
                            @if ($hasFilters)
                                <a href="{{ route('legal-documents.index') }}">សម្អាតការស្វែងរក</a>
                            @endif
                        </div>
                    @else
                        <ul class="legal-doc-list" aria-label="បញ្ជីលិខិតបទដ្ឋានគតិយុត្ត">
                            @foreach ($documents as $document)
                                @php
                                    $fileSize = $document->formattedFileSize();
                                    $format = $document->fileExtension();
                                    // Uploaded files stream through our own route; externally hosted ones open in a new tab.
                                    $isExternal = ! $document->file_path && $document->external_url;
                                    $downloadUrl = $isExternal ? $document->external_url : route('legal-documents.download', $document);
                                    $linkAttrs = $isExternal ? ' target="_blank" rel="noopener noreferrer"' : '';
                                    $note =\Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $document->description))), 160);
                                @endphp
                                <li class="legal-doc">
                                    <span class="legal-doc-icon is-{{ $document->iconType() }}" aria-hidden="true">
                                        <x-news-icon name="document" width="24" height="24" />
                                        <span>{{ $tileLabels[$document->iconType()] ?? 'FILE' }}</span>
                                    </span>

                                    <div class="legal-doc-body">
                                        <h3 class="legal-doc-title">
                                            @if ($document->hasDownload())
                                                <a href="{{ $downloadUrl }}"{!! $linkAttrs !!}>{{ $document->title }}</a>
                                            @else
                                                {{ $document->title }}
                                            @endif
                                        </h3>
                                        <div class="legal-doc-badges">
                                            @if ($document->category)
                                                <span class="legal-badge is-type is-tone-{{ ($categoryTone[$document->category_id] ?? 0) % 6 }}">{{ $document->category->name }}</span>
                                            @endif
                                            <span class="legal-badge is-body">{{ $document->issuing_authority }}</span>
                                            @if ($document->year)
                                                <span class="legal-badge is-year">ឆ្នាំ {{ KhmerDate::toKhmerDigits((string) $document->year) }}</span>
                                            @endif
                                        </div>
                                        @if (filled($document->document_number) || $note !== '')
                                            <p class="legal-doc-note">
                                                @if (filled($document->document_number))
                                                    <span class="legal-document-number">លេខឯកសារ៖ {{ $document->document_number }}</span>@if ($note !== '') <span class="legal-doc-excerpt">{{ $note }}</span>@endif
                                                @else
                                                    {{ $note }}
                                                @endif
                                            </p>
                                        @endif
                                    </div>

                                    <dl class="legal-doc-meta">
                                        @if ($document->issue_date)
                                            <div>
                                                <dt class="sr-only">កាលបរិច្ឆេទ</dt>
                                                <dd><x-news-icon name="calendar" width="17" height="17" /><time datetime="{{ $document->issue_date->toDateString() }}">{{ $document->issue_date->format('d-m-Y') }}</time></dd>
                                            </div>
                                        @endif
                                        @if ($fileSize || $format !== '')
                                            <div>
                                                <dt class="sr-only">ទំហំឯកសារ</dt>
                                                <dd><x-news-icon name="document" width="17" height="17" />{{ $format }}@if ($fileSize) · {{ $fileSize }}@endif</dd>
                                            </div>
                                        @endif
                                    </dl>

                                    <div class="legal-doc-action">
                                        @if ($document->hasDownload())
                                            <a href="{{ $downloadUrl }}"{!! $linkAttrs !!} class="legal-download" aria-label="ទាញយក៖ {{ $document->title }}"><x-news-icon name="download" width="18" height="18" />ទាញយក</a>
                                        @else
                                            <span class="legal-file-unavailable">មិនទាន់មានឯកសារ</span>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>

                        <div class="legal-pagination">
                            <p class="legal-pagination-summary">បង្ហាញ {{ KhmerDate::toKhmerDigits((string) $documents->firstItem()) }}-{{ KhmerDate::toKhmerDigits((string) $documents->lastItem()) }} នៃ {{ KhmerDate::toKhmerDigits((string) $documents->total()) }} ឯកសារ</p>
                            {{ $documents->onEachSide(1)->fragment('legal-results')->links('legal-documents.pagination') }}
                            <p class="legal-pagination-pages">ទំព័រ {{ KhmerDate::toKhmerDigits((string) $documents->currentPage()) }} នៃ {{ KhmerDate::toKhmerDigits((string) $documents->lastPage()) }}</p>
                        </div>
                    @endif
                </section>
            </div>

            <aside class="legal-sidebar" aria-label="ប្រភេទឯកសារ និងតំណភ្ជាប់ប្រយោជន៍">
                <section class="legal-category-card" x-data="{ open: false }" :class="{ 'is-open': open }">
                    <div class="legal-card-title">
                        <h2 id="legal-categories-heading"><x-news-icon name="categories" width="20" height="20" />ប្រភេទឯកសារ</h2>
                        <button type="button" class="legal-category-toggle" aria-controls="legal-category-menu" :aria-expanded="open.toString()" aria-expanded="false" @click="open = ! open">
                            <span>{{ $activeCategory?->name ?? 'ទាំងអស់' }}</span>
                            <x-news-icon name="chevron-right" width="16" height="16" />
                        </button>
                    </div>
                    <nav id="legal-category-menu" class="legal-category-menu" aria-labelledby="legal-categories-heading">
                        <a href="{{ route('legal-documents.index', $searchFilters) }}#legal-results" class="legal-category-link {{ $currentType === '' ? 'is-active' : '' }}" @if ($currentType === '') aria-current="page" @endif>
                            <x-news-icon name="document" />
                            <span class="legal-category-name">ទាំងអស់</span>
                            <span class="legal-category-count">{{ KhmerDate::toKhmerDigits((string) $publishedCount) }}</span>
                            <x-news-icon name="chevron-right" />
                        </a>
                        @foreach ($categories as $category)
                            <a href="{{ route('legal-documents.index', [...$searchFilters, 'type' => $category->id]) }}#legal-results" class="legal-category-link {{ $currentType === (string) $category->id ? 'is-active' : '' }}" @if ($currentType === (string) $category->id) aria-current="page" @endif>
                                <x-news-icon name="document" />
                                <span class="legal-category-name">{{ $category->name }}</span>
                                <span class="legal-category-count">{{ KhmerDate::toKhmerDigits((string) $category->documents_count) }}</span>
                                <x-news-icon name="chevron-right" />
                            </a>
                        @endforeach
                    </nav>
                </section>

                @if ($usefulLinks->isNotEmpty())
                    <section class="legal-links-card" aria-labelledby="legal-links-heading">
                        <div class="legal-card-title">
                            <h2 id="legal-links-heading"><x-news-icon name="link" width="20" height="20" />តំណភ្ជាប់ប្រយោជន៍</h2>
                        </div>
                        <ul class="legal-links-list">
                            @foreach ($usefulLinks as $link)
                                <li>
                                    <a href="{{ $link['url'] }}" @if ($link['external']) target="_blank" rel="noopener noreferrer" @endif>
                                        <span class="legal-link-label">
                                            <span class="legal-link-icon"><x-news-icon name="link" width="14" height="14" /></span>
                                            <span>{{ $link['label'] }}</span>
                                        </span>
                                        <x-news-icon :name="$link['external'] ? 'external' : 'chevron-right'" width="16" height="16" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </aside>
        </div>
    </main>
</x-layout>
