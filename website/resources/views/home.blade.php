<x-layout>
    <x-hero-slider :slides="$heroSlides" />

    <x-news-ticker :articles="$tickerNews" :duration="$tickerDuration" />

    <div class="home-sections">
        {{-- Leader Recommendations --}}
        @if ($leaderRecommendation)
            <x-leader-recommendation-section :recommendation="$leaderRecommendation" />
        @endif

        {{-- Latest News & Activities --}}
        @if ($featuredNews)
            <section class="home-section">
                <div class="home-section-header">
                    <h2 class="home-section-title">ព័ត៌មាន និងសកម្មភាពថ្មីៗ</h2>
                    <a href="{{ route('news.index') }}" class="home-section-more">មើលទាំងអស់ →</a>
                </div>
                <div class="home-news-layout">
                    <x-featured-news :article="$featuredNews" />
                    @if ($secondaryNews->isNotEmpty())
                        <div class="home-news-small-list">
                            @foreach ($secondaryNews as $article)
                                <x-news-card-small :article="$article" />
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Same News section, no second heading: a mixed-content strip of
                     the newest News/Health Education/Legal Document items. --}}
                @if ($latestContent->isNotEmpty())
                    <x-latest-content-slider :items="$latestContent" />
                @endif
            </section>
        @endif

        {{-- Quick Access (CMS-managed: admin/homepage-quick-links) --}}
        @if ($quickLinks->isNotEmpty())
            <section class="home-section">
                <div class="home-section-header home-quick-section-header">
                    <h2 class="home-section-title">ផ្លូវកាត់សំខាន់ៗ</h2>
                </div>
                <div class="home-quick-grid">
                    @foreach ($quickLinks as $quickLink)
                        <x-quick-access-card :link="$quickLink" />
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Health Education: featured video + 3 compact video cards --}}
        @if ($featuredHealthVideo)
            <section class="home-section" x-data="{ videoOpen: false, videoEmbed: null }">
                <div class="home-section-header home-health-section-header">
                    <div>
                        <h2 class="home-section-title">មាតិកាអប់រំសុខភាព</h2>
                        <p class="home-health-subtitle">វីដេអូអប់រំសុខភាពសម្រាប់ប្រជាពលរដ្ឋ ដើម្បីបង្កើនការយល់ដឹង និងលើកកម្ពស់សុខភាពប្រចាំថ្ងៃ</p>
                    </div>
                    <a href="{{ route('health-content.index') }}" class="home-section-more">មើលវីដេអូទាំងអស់ →</a>
                </div>
                <div class="home-health-layout">
                    <x-featured-health-video :video="$featuredHealthVideo" />
                    @if ($secondaryHealthVideos->isNotEmpty())
                        <div class="home-health-small-list">
                            @foreach ($secondaryHealthVideos as $video)
                                <x-health-video-card-small :video="$video" />
                            @endforeach
                        </div>
                    @endif
                </div>

                <div x-show="videoOpen" x-cloak x-transition.opacity class="home-video-modal"
                     @keydown.escape.window="videoOpen = false" @click.self="videoOpen = false">
                    <div class="home-video-modal-inner">
                        <button type="button" class="home-video-modal-close" @click="videoOpen = false" aria-label="បិទ">✕</button>
                        <div class="home-video-modal-frame">
                            <template x-if="videoOpen && videoEmbed?.type === 'iframe'">
                                <iframe :src="videoEmbed.src + (videoEmbed.src.includes('?') ? '&' : '?') + 'autoplay=1'" frameborder="0"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                            </template>
                            <template x-if="videoOpen && videoEmbed?.type === 'video'">
                                <video :src="videoEmbed.src" controls autoplay></video>
                            </template>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- Latest Legal Documents / Announcements --}}
        @if ($legalDocuments->isNotEmpty())
            <section class="home-section">
                <div class="home-section-header">
                    <h2 class="home-section-title">សេចក្តីជូនដំណឹង និងលិខិតថ្មីៗ</h2>
                    <a href="{{ route('legal-documents.index') }}" class="home-section-more">មើលទាំងអស់ →</a>
                </div>
                <div class="home-documents-list">
                    @foreach ($legalDocuments as $document)
                        <x-document-item :document="$document" />
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Partners --}}
        @if ($partners->isNotEmpty())
            <section class="home-section">
                <div class="home-section-header">
                    <h2 class="home-section-title">អង្គការដៃគូ</h2>
                </div>
                <div class="home-partners-grid">
                    @foreach ($partners as $partner)
                        <x-partner-card :partner="$partner" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layout>
