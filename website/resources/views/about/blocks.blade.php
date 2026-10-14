@foreach ($blocks as $block)
    @if (in_array($block->block_type, ['image', 'image_text']))
        @php
            $media = $block->get('media_id') ? \App\Models\Media::find($block->get('media_id')) : null;
            $src = $media?->isImage() ? $media->url : (! $block->get('media_id') ? \App\Models\Media::resolveLegacyPath($block->get('image')) : null);
        @endphp
        <div class="{{ $block->block_type === 'image_text' ? 'about-image-text' : 'about-image' }}">
            @if ($block->block_type === 'image_text')<div class="about-prose">@if ($block->get('heading'))<h3>{{ $block->get('heading') }}</h3>@endif{!! $block->get('html') !!}</div>@endif
            @if ($src)<figure><img src="{{ $src }}" alt="{{ $block->get('alt_text') ?: $media?->alt_text ?: config('site.name_km') }}" width="640" height="480" loading="lazy">@if ($block->get('caption'))<figcaption>{{ $block->get('caption') }}</figcaption>@endif</figure>@endif
        </div>
    @elseif ($block->block_type === 'heading')
        <h3>{{ $block->get('text') }}</h3>
    @else
        <x-page-blocks.render-block :block="$block" />
    @endif
@endforeach
