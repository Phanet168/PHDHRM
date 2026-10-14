<div class="about-leaders-wrap" data-about-carousel>
    @if ($leaders->count() > 1)
        <button type="button" class="about-carousel-button is-prev" data-about-carousel-prev aria-label="ថ្នាក់ដឹកនាំមុន" aria-controls="about-leaders-track"><x-news-icon name="chevron-left" /></button>
    @endif
    <div id="about-leaders-track" class="about-leaders" data-about-carousel-track tabindex="0" role="region" aria-label="ថ្នាក់ដឹកនាំ — អូស ឬប្រើព្រួញដើម្បីមើលបន្ថែម">
        @foreach ($leaders as $leader)
            @php $portrait = $leader->portraitUrl(); @endphp
            <article @class(['about-leader', 'is-featured' => $leader->is_featured])>
                @if ($portrait)
                    <img src="{{ $portrait }}" alt="{{ $leader->name }} — {{ $leader->title }}" width="360" height="420" loading="lazy" decoding="async">
                @else
                    <div class="about-portrait-placeholder" aria-hidden="true"><x-news-icon name="user" width="68" height="68" /></div>
                @endif
                <div class="about-leader-body">
                    <h3>{{ $leader->name }}</h3>
                    <p>{{ $leader->title }}</p>
                    @if ($leader->bio)<p class="about-leader-bio">{{ $leader->bio }}</p>@endif
                </div>
            </article>
        @endforeach
    </div>
    @if ($leaders->count() > 1)
        <button type="button" class="about-carousel-button is-next" data-about-carousel-next aria-label="ថ្នាក់ដឹកនាំបន្ទាប់" aria-controls="about-leaders-track"><x-news-icon name="chevron-right" /></button>
    @endif
    <div class="about-carousel-dots" data-about-carousel-dots aria-label="ទំព័រថ្នាក់ដឹកនាំ"></div>
</div>
