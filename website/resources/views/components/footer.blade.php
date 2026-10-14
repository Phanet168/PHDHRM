<footer class="public-footer">
    <div class="public-container public-footer-grid">
        <div class="public-footer-about">
            <a href="{{ route('home') }}" class="public-footer-brand">
                <x-organization-logo class="public-footer-logo" />
                <div class="public-footer-brand-text">
                    <p class="public-footer-name">{{ config('site.name_km') }}</p>
                    <p class="public-footer-name-en" lang="en">{{ config('site.name_en') }}</p>
                </div>
            </a>
            <p class="public-footer-description">ព័ត៌មានសុខាភិបាល មូលដ្ឋានសុខាភិបាល និងការផ្សព្វផ្សាយសុខភាពសាធារណៈ។</p>
            @if (config('site.facebook_url') || config('site.telegram_url') || config('site.youtube_url'))
                <div class="public-footer-socials" aria-label="បណ្ដាញសង្គម">
                    @foreach (['facebook' => 'Facebook', 'telegram' => 'Telegram', 'youtube' => 'YouTube'] as $platform => $label)
                        @if (config('site.' . $platform . '_url'))
                            <a href="{{ config('site.' . $platform . '_url') }}" target="_blank" rel="noopener noreferrer">
                                {{ $label }} <x-news-icon name="arrow" width="16" height="16" />
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        <nav class="public-footer-column" aria-labelledby="footer-links-heading">
            <h2 id="footer-links-heading" class="public-footer-heading">តំណភ្ជាប់រហ័ស</h2>
            <ul class="public-footer-links">
                @foreach (\App\Models\Menu::tree('footer') as $item)
                    <li>
                        <a href="{{ $item->resolveUrl() }}" target="{{ $item->target }}" @if ($item->target === '_blank') rel="noopener noreferrer" @endif>
                            <x-news-icon name="chevron-right" width="16" height="16" />
                            <span>{{ $item->label_km }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="public-footer-column">
            <h2 class="public-footer-heading">ទំនាក់ទំនង</h2>
            <ul class="public-footer-contacts">
                @if (config('site.phone'))
                    <li>
                        <span class="public-footer-contact-icon"><x-news-icon name="phone" /></span>
                        <div><span class="public-footer-contact-label">ទូរស័ព្ទ</span><a href="tel:{{ preg_replace('/[^0-9+]/', '', config('site.phone')) }}">{{ config('site.phone') }}</a></div>
                    </li>
                @endif
                @if (config('site.email'))
                    <li>
                        <span class="public-footer-contact-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/></svg>
                        </span>
                        <div><span class="public-footer-contact-label">អ៊ីមែល</span><a href="mailto:{{ config('site.email') }}">{{ config('site.email') }}</a></div>
                    </li>
                @endif
                @if (config('site.address'))
                    <li class="public-footer-contact-address">
                        <span class="public-footer-contact-icon"><x-news-icon name="pin" /></span>
                        <div><span class="public-footer-contact-label">អាសយដ្ឋាន</span><p>{{ config('site.address') }}</p></div>
                    </li>
                @endif
            </ul>
        </div>
    </div>

    <div class="public-footer-bottom">
        <div class="public-container public-footer-bottom-inner">
            <p>© {{ now()->year }} {{ config('site.name_km') }}. រក្សាសិទ្ធិគ្រប់យ៉ាង។</p>
            <div class="public-footer-social-icons" aria-label="បណ្ដាញសង្គម">
                @foreach (['telegram' => 'Telegram', 'facebook' => 'Facebook'] as $platform => $label)
                    @if (config('site.' . $platform . '_url'))
                        <a class="public-footer-social-icon is-{{ $platform }}" href="{{ config('site.' . $platform . '_url') }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $label }}" title="{{ $label }}">
                    @else
                        <span class="public-footer-social-icon is-{{ $platform }}" role="img" aria-label="{{ $label }}" title="{{ $label }}">
                    @endif
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            @if ($platform === 'telegram')
                                <path d="M21.4 3.6 18.1 20c-.25 1.16-.9 1.44-1.83.9l-5.03-3.71-2.42 2.33c-.27.27-.5.5-1.02.5l.36-5.12 9.33-8.43c.4-.36-.09-.56-.62-.21L5.34 13.52.37 11.97c-1.08-.34-1.1-1.08.23-1.6L20.04 2.9c.9-.34 1.68.2 1.36.7Z"/>
                            @else
                                <path d="M15.7 8H19l-.5 4h-2.8v10h-4.2V12H8V8h3.5V5.5C11.5 2.6 13.2 1 15.8 1c1.25 0 2.33.09 2.65.14v3.1h-1.82c-1.42 0-1.69.67-1.69 1.66V8Z"/>
                            @endif
                        </svg>
                    @if (config('site.' . $platform . '_url'))
                        </a>
                    @else
                        </span>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</footer>
