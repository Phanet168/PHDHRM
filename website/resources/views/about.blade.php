@php
    // Page-block extras from the older Pages-based About page (empty when no published About page exists).
    $page = $legacy['page'];
    $blocks = $legacy['sections'];
    $hasLeadership = $leaders->isNotEmpty() || $blocks['leadership']->isNotEmpty();
    $officeGroup = $organization->firstWhere('type', 'office') ? 'office' : ($organization->firstWhere('type', 'operational_district') ? 'operational_district' : null);
    $hasStructure = $organization->isNotEmpty() || $blocks['structure']->isNotEmpty() || $blocks['offices']->isNotEmpty();
    $hasTimeline = $timeline->isNotEmpty() || $blocks['timeline']->isNotEmpty();
    $hasFiles = $documents->isNotEmpty() || $blocks['files']->isNotEmpty();

    $menu = collect();
    if ($history) $menu->put('history', $history['title']);
    foreach ($cards as $card) $menu->put($card['anchor'], $card['title']);
    if ($mission && $missionAnchor) $menu->put('mission', $mission['title']);
    foreach (['vision', 'mission', 'roles', 'values'] as $key) {
        if ($blocks[$key]->isNotEmpty() && ! $menu->has($key)) $menu->put($key, \App\Services\AboutPageContent::SECTIONS[$key]);
    }
    if ($hasTimeline) $menu->put('timeline', 'ប្រវត្តិ និងការអភិវឌ្ឍ');
    if ($hasStructure) $menu->put('structure', 'រចនាសម្ព័ន្ធ');
    if ($hasLeadership) $menu->put('leadership', 'ថ្នាក់ដឹកនាំ');
    if ($officeGroup || $blocks['offices']->isNotEmpty()) $menu->put('offices', 'ការិយាល័យចំណុះ');
    $menu->put('about-contact', 'ទីតាំង និងទំនាក់ទំនង');
    $active = $menu->keys()->first();
    $menuIcons = ['history' => 'document', 'vision' => 'eye', 'mission' => 'target', 'roles' => 'briefcase', 'values' => 'shield', 'timeline' => 'trending-up', 'structure' => 'categories', 'leadership' => 'users', 'offices' => 'hospital', 'about-contact' => 'pin']
        + $cards->pluck('icon', 'anchor')->all();
    $mapUrl = config('site.map_url') ?: 'https://www.google.com/maps/search/?api=1&query='.urlencode(config('site.name_en'));
@endphp
<x-layout :title="$hero['title'] ?? 'អំពីមន្ទីរ'" :meta-description="$page?->metaDescriptionOrFallback() ?: ($hero['subtitle'] ?? null)">
    <div class="about-page" data-about-page>
        @if ($hero)
            <section class="about-hero" aria-labelledby="about-title">
                <img src="{{ $hero['image'] }}" alt="" fetchpriority="high" width="1672" height="941">
                <div class="public-container about-hero-content">
                    <div>
                        <nav aria-label="ទីតាំងទំព័រ" class="about-breadcrumb"><a href="{{ route('home') }}"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3 2 11.5h3V21h5.5v-6h3v6H19v-9.5h3z"/></svg>ទំព័រដើម</a><x-news-icon name="chevron-right" width="14" height="14" /><span aria-current="page">{{ $hero['title'] }}</span></nav>
                        <h1 id="about-title">{{ $hero['title'] }}</h1>
                        @if ($hero['subtitle'])<p>{{ $hero['subtitle'] }}</p>@endif
                    </div>
                    @if ($hero['intro'])<p class="about-hero-quote">{{ $hero['intro'] }}</p>@endif
                </div>
            </section>
        @else
            <h1 id="about-title" class="sr-only">អំពីមន្ទីរ</h1>
        @endif

        <div class="public-container about-layout">
            @if ($menu->isNotEmpty())
                <details class="about-mobile-nav" data-about-dropdown>
                    <summary aria-expanded="false" aria-controls="about-mobile-links"><span><small>អំពីមន្ទីរ</small><strong data-about-current>{{ $menu[$active] }}</strong></span><x-news-icon name="chevron-right" /></summary>
                    <nav id="about-mobile-links" aria-label="មាតិកាអំពីមន្ទីរ">
                        @include('about.navigation')
                    </nav>
                </details>
            @endif

            <main class="about-main">
                @if ($history)
                    <section id="history" class="about-section" aria-labelledby="history-title">
                        @include('about.heading', ['id' => 'history-title', 'icon' => 'document', 'title' => $history['title']])
                        <div @class(['about-history', 'has-cms-image' => ! $history['image'], 'is-image-left' => $history['position'] === 'left'])>
                            <div class="about-history-text" data-about-clamp>
                                <div id="about-history-prose" class="about-prose">
                                    @if (! $history['legacy'])
                                        {!! $history['html'] !!}
                                    @elseif ($page)
                                        @include('about.blocks', ['blocks' => $blocks['history']])
                                        @if ($page->content && $blocks['history']->isEmpty())<p>{{ $page->content }}</p>@endif
                                    @else
                                        <p>{{ config('about.history') }}</p>
                                    @endif
                                </div>
                                {{-- Shown by about.js only when the text is longer than the clamp. --}}
                                <button type="button" class="about-more-button" data-about-clamp-toggle aria-controls="about-history-prose" aria-expanded="false" hidden><span data-label-more>អានបន្ថែម</span><span data-label-less hidden>បង្ហាញតិចវិញ</span><x-news-icon name="arrow" width="16" height="16" /></button>
                            </div>
                            @if ($history['image'])
                                <img class="about-history-image" src="{{ $history['image'] }}" alt="{{ $history['alt'] }}" width="640" height="440" loading="lazy" decoding="async">
                            @endif
                        </div>
                        @unless ($history['legacy'])@include('about.blocks', ['blocks' => $blocks['history']])@endunless
                    </section>
                @endif

                @if ($mission)
                    <section class="about-mission" @if ($missionAnchor) id="mission" @endif aria-labelledby="mission-quote-title">
                        <span class="about-icon"><x-news-icon :name="$mission['icon']" width="30" height="30" /></span>
                        <div><h2 id="mission-quote-title">{{ $mission['title'] }}</h2><blockquote>“{{ $mission['text'] }}”@if ($mission['author'])<cite>{{ $mission['author'] }}</cite>@endif</blockquote></div>
                    </section>
                @endif

                @if ($cards->isNotEmpty())
                    <div class="about-core-grid">
                        @foreach ($cards as $card)
                            <section id="{{ $card['anchor'] }}" class="about-core-card accent-{{ $card['accent'] }}" aria-labelledby="{{ $card['anchor'] }}-title">
                                <span class="about-icon"><x-news-icon :name="$card['icon']" width="26" height="26" /></span>
                                <h2 id="{{ $card['anchor'] }}-title">{{ $card['title'] }}</h2>
                                @if ($card['text'])<p>{{ $card['text'] }}</p>@endif
                                @if ($card['link'])<a href="{{ $card['link'] }}">អានបន្ថែម<span class="sr-only">៖ {{ $card['title'] }}</span><x-news-icon name="arrow" width="16" height="16" /></a>@endif
                            </section>
                        @endforeach
                    </div>
                @endif
                @foreach (['vision', 'mission', 'roles', 'values'] as $key)
                    @if ($blocks[$key]->isNotEmpty())
                        <section class="about-section about-prose" @if (! $cards->contains('anchor', $key) && ! ($key === 'mission' && $mission && $missionAnchor)) id="{{ $key }}" @endif>
                            @include('about.heading', ['id' => $key.'-detail-title', 'icon' => ['vision' => 'eye', 'mission' => 'target', 'roles' => 'briefcase', 'values' => 'shield'][$key], 'title' => \App\Services\AboutPageContent::SECTIONS[$key]])
                            @include('about.blocks', ['blocks' => $blocks[$key]])
                        </section>
                    @endif
                @endforeach

                @if ($hasTimeline)
                    <section id="timeline" class="about-section" aria-labelledby="timeline-title">
                        @include('about.heading', ['id' => 'timeline-title', 'icon' => 'trending-up', 'title' => 'ប្រវត្តិ និងការអភិវឌ្ឍ'])
                        @include('about.timeline')
                        @include('about.blocks', ['blocks' => $blocks['timeline']])
                    </section>
                @endif

                @if ($hasLeadership)
                    <section id="leadership" class="about-section" aria-labelledby="leadership-title">
                        @include('about.heading', ['id' => 'leadership-title', 'icon' => 'users', 'title' => 'ថ្នាក់ដឹកនាំ'])
                        @include('about.blocks', ['blocks' => $blocks['leadership']])
                        @if ($leaders->isNotEmpty())
                            @include('about.leaders')
                        @endif
                    </section>
                @endif

                @if ($hasStructure)
                    <section id="structure" class="about-section" aria-labelledby="structure-title">
                        <div class="about-heading-row">
                            @include('about.heading', ['id' => 'structure-title', 'icon' => 'categories', 'title' => 'រចនាសម្ព័ន្ធអង្គភាព'])
                            @if ($organization->isNotEmpty())
                                <button type="button" class="about-outline-button" data-about-org-toggle aria-controls="about-org-detail" aria-expanded="true"><span data-label-more>មើលរចនាសម្ព័ន្ធពេញលេញ</span><span data-label-less hidden>បិទបញ្ជីលម្អិត</span><x-news-icon name="arrow" width="16" height="16" /></button>
                            @endif
                        </div>
                        @include('about.blocks', ['blocks' => $blocks['structure']])
                        @if ($organization->isNotEmpty())
                            @include('about.organization')
                        @endif
                        @if ($blocks['offices']->isNotEmpty())
                            <div @unless ($officeGroup) id="offices" @endunless class="about-prose">
                                @include('about.heading', ['id' => 'offices-title', 'icon' => 'users', 'title' => 'ការិយាល័យចំណុះ'])
                                @include('about.blocks', ['blocks' => $blocks['offices']])
                            </div>
                        @endif
                    </section>
                @endif

                <section id="about-contact" class="about-section about-contact" aria-labelledby="about-contact-title">
                    <div class="about-contact-body">
                        <div class="about-contact-info">
                            @include('about.heading', ['id' => 'about-contact-title', 'icon' => 'pin', 'title' => 'ទីតាំង និងទំនាក់ទំនង'])
                            @include('about.contact', ['compact' => false])
                            @include('about.blocks', ['blocks' => $blocks['contact']])
                        </div>
                        @if ($map)
                            <div class="about-map-frame">
                                <div class="about-map" data-about-map data-lat="{{ $map['lat'] }}" data-lng="{{ $map['lng'] }}" data-zoom="{{ $map['zoom'] }}" data-marker-title="{{ $map['title'] }}" role="region" aria-label="ផែនទីទីតាំង {{ config('site.name_km') }}" title="ផែនទីទីតាំង {{ config('site.name_km') }}"></div>
                                <a class="about-map-expand" href="{{ $mapUrl }}" target="_blank" rel="noopener noreferrer" aria-label="បើកផែនទីធំលើ Google Maps"><x-news-icon name="external" width="16" height="16" /></a>
                            </div>
                        @else
                            @include('about.map-placeholder')
                        @endif
                    </div>
                </section>
            </main>

            <aside class="about-sidebar" aria-label="ព័ត៌មានបន្ថែមអំពីមន្ទីរ">
                @if ($menu->isNotEmpty())
                    <div class="about-sidebar-nav about-side-card">
                        <h2>មាតិកា</h2>
                        <nav aria-label="មាតិកាអំពីមន្ទីរ">@include('about.navigation')</nav>
                    </div>
                @endif
                @if ($hasFiles)
                    <section class="about-side-card about-files" aria-labelledby="about-files-title">
                        <div class="about-side-head"><h2 id="about-files-title">ឯកសារពាក់ព័ន្ធ</h2><a href="{{ route('legal-documents.index') }}">មើលទាំងអស់<x-news-icon name="arrow" width="14" height="14" /></a></div>
                        @foreach ($documents as $file)
                            <a class="about-file" href="{{ $file['url'] }}" target="_blank" rel="noopener noreferrer"><x-media-file-icon :type="$file['icon']" /><span><span class="about-file-title" title="{{ $file['title'] }}">{{ $file['title'] }}</span><small>{{ $file['type'] }}@if ($file['size']) · {{ $file['size'] }}@endif</small></span><span class="about-file-action"><x-news-icon :name="$file['external'] ? 'external' : 'download'" width="16" height="16" /></span><span class="sr-only">(បើកក្នុងផ្ទាំងថ្មី)</span></a>
                        @endforeach
                        @include('about.blocks', ['blocks' => $blocks['files']])
                    </section>
                @endif
                <section class="about-side-card about-sidebar-contact" aria-labelledby="about-sidebar-contact-title">
                    <h2 id="about-sidebar-contact-title">ទំនាក់ទំនង</h2>
                    @include('about.contact', ['compact' => true])
                </section>
                <section class="about-side-card about-sidebar-map" aria-labelledby="about-sidebar-map-title">
                    <h2 id="about-sidebar-map-title">ទីតាំងលើផែនទី</h2>
                    @include('about.map-placeholder', ['small' => true])
                </section>
            </aside>
        </div>
    </div>
</x-layout>
