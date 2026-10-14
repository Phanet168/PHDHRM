{{-- Reads the same site settings as the header, footer and contact page. --}}
<dl class="about-contact-list {{ $compact ? 'is-compact' : '' }}">
    @foreach (['address' => ['pin', 'អាសយដ្ឋាន'], 'phone' => ['phone', 'ទូរស័ព្ទ'], 'email' => ['mail', 'អ៊ីមែល'], 'hours' => ['clock', 'ម៉ោងធ្វើការ'], 'website' => ['globe', 'គេហទំព័រ']] as $key => [$icon, $label])
        @if (config('site.'.$key) && (! $compact || $key !== 'website'))
            <div><dt><span class="about-contact-icon"><x-news-icon :name="$icon" width="18" height="18" /></span><span class="sr-only">{{ $label }}</span></dt><dd>
                @if ($key === 'phone')<a href="tel:{{ preg_replace('/[^0-9+]/', '', config('site.phone')) }}">{{ config('site.phone') }}</a>
                @elseif ($key === 'email')<a href="mailto:{{ config('site.email') }}">{{ config('site.email') }}</a>
                @elseif ($key === 'website')<a href="{{ config('site.website') }}" target="_blank" rel="noopener noreferrer">{{ preg_replace('#^https?://#', '', config('site.website')) }}</a>
                @else{{ config('site.'.$key) }}@endif
            </dd></div>
        @endif
    @endforeach
</dl>
@unless ($compact)
    <div class="about-social">
        @foreach (['facebook_url' => 'Facebook', 'telegram_url' => 'Telegram', 'youtube_url' => 'YouTube'] as $key => $label)
            @if (config('site.'.$key))<a href="{{ config('site.'.$key) }}" target="_blank" rel="noopener noreferrer">{{ $label }}<x-news-icon name="external" width="16" height="16" /></a>@endif
        @endforeach
    </div>
@endunless
