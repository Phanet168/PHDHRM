@props(['slides'])

@if ($slides->isNotEmpty())
    <div class="public-hero hero-swiper swiper relative h-[330px] md:h-[390px] lg:h-[450px] overflow-hidden">
        <div class="swiper-wrapper">
            @foreach ($slides as $slide)
                <div class="swiper-slide relative">
                    <img src="{{ asset('storage/' . $slide->image) }}" alt="{{ $slide->title_km }}" class="hero-slide-image absolute inset-0 size-full object-cover">
                    <div class="absolute inset-0 bg-brand-navy opacity-[0.52]"></div>
                    <div class="public-container public-hero-content">
                        <div class="hero-slide-content absolute left-[var(--public-gutter)] top-[55px] md:top-[85px] lg:top-[105px] max-w-[610px] pr-6 md:pr-0">
                            <h1 class="font-bold text-[26px] md:text-[36px] lg:text-[44px] text-white leading-[1.4]">{{ $slide->title_km }}</h1>
                        </div>
                        @if ($slide->description)
                            <p class="hero-slide-content absolute left-[var(--public-gutter)] top-[140px] md:top-[190px] lg:top-[245px] max-w-[570px] pr-6 md:pr-0 text-[14px] md:text-[16px] lg:text-[17px] text-white">
                                {{ $slide->description }}
                            </p>
                        @endif
                        <div class="hero-slide-content absolute left-[var(--public-gutter)] bottom-[36px] md:bottom-[50px] flex gap-3">
                            @if ($slide->button_text && $slide->button_link)
                                <a href="{{ $slide->button_link }}" class="bg-brand-green flex items-center justify-center h-[40px] md:h-[42px] px-6 rounded-[9px] font-bold text-[14px] md:text-[15px] text-white">
                                    {{ $slide->button_text }} →
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($slides->count() > 1)
            <button type="button" class="hero-swiper-prev hero-swiper-nav" aria-label="មុន"><x-news-icon name="chevron-left" width="20" height="20" /></button>
            <button type="button" class="hero-swiper-next hero-swiper-nav" aria-label="បន្ទាប់"><x-news-icon name="chevron-right" width="20" height="20" /></button>
            <div class="hero-swiper-pagination swiper-pagination"></div>
        @endif
    </div>
@else
    <div class="public-hero relative h-[330px] md:h-[390px] lg:h-[450px] overflow-hidden">
        <img src="{{ asset('images/stock/stock-1.jpg') }}" alt="" class="absolute inset-0 size-full object-cover">
        <div class="absolute inset-0 bg-brand-navy opacity-[0.52]"></div>
        <div class="public-container public-hero-content">
            <div class="absolute left-[var(--public-gutter)] top-[55px] md:top-[85px] lg:top-[105px] max-w-[610px] pr-6 md:pr-0">
                <h1 class="font-bold text-[26px] md:text-[36px] lg:text-[44px] text-white leading-[1.4]">
                    មន្ទីរសុខាភិបាល<br>ដើម្បីសុខភាពប្រជាពលរដ្ឋ
                </h1>
            </div>
            <p class="absolute left-[var(--public-gutter)] top-[140px] md:top-[190px] lg:top-[245px] max-w-[570px] pr-6 md:pr-0 text-[14px] md:text-[16px] lg:text-[17px] text-white">
                ព័ត៌មានសុខាភិបាល មូលដ្ឋានសុខាភិបាល និងការផ្សព្វផ្សាយសុខភាពសម្រាប់ប្រជាពលរដ្ឋក្នុងខេត្តស្ទឹងត្រែង។
            </p>
            <div class="absolute left-[var(--public-gutter)] bottom-[36px] md:bottom-[50px] flex gap-3">
                <a href="{{ route('facilities.index') }}" class="bg-brand-green flex items-center justify-center h-[40px] md:h-[42px] px-6 rounded-[9px] font-bold text-[14px] md:text-[15px] text-white">
                    ស្វែងយល់បន្ថែម →
                </a>
            </div>
        </div>
    </div>
@endif
