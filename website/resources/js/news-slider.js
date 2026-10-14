import { Swiper } from 'swiper';
import { A11y, Autoplay, Navigation, Pagination } from 'swiper/modules';

export function initNewsSlider() {
    const sidebar = document.querySelector('.news-sidebar-stack');
    if (sidebar) {
        // The sidebar pins below the sticky header, so only the space under it counts.
        const updateSticky = () => {
            const headerHeight = document.querySelector('[data-sticky-header]')?.offsetHeight ?? 0;
            sidebar.classList.toggle('is-sticky', sidebar.offsetHeight + headerHeight + 40 <= window.innerHeight);
        };
        new ResizeObserver(updateSticky).observe(sidebar);
        window.addEventListener('resize', updateSticky);
        updateSticky();
    }

    const el = document.querySelector('.news-swiper');
    if (!el) return;

    const multiple = el.querySelectorAll('.swiper-slide').length > 1;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const pauseButton = el.querySelector('.news-slider-pause');
    let paused = reducedMotion.matches;
    let focused = false;
    let hovered = false;

    const syncSlides = (swiper) => {
        swiper.slides.forEach((slide, index) => {
            slide.inert = index !== swiper.activeIndex;
            slide.setAttribute('aria-hidden', String(index !== swiper.activeIndex));
        });
    };

    const swiper = new Swiper(el, {
        modules: [A11y, Autoplay, Navigation, Pagination],
        slidesPerView: 1,
        speed: reducedMotion.matches ? 0 : 750,
        rewind: true,
        grabCursor: multiple,
        watchOverflow: true,
        autoplay: multiple ? { delay: 5500, disableOnInteraction: false } : false,
        navigation: {
            prevEl: el.querySelector('.news-slider-prev'),
            nextEl: el.querySelector('.news-slider-next'),
        },
        pagination: {
            el: el.querySelector('.news-slider-pagination'),
            clickable: true,
            bulletElement: 'button',
        },
        a11y: {
            prevSlideMessage: 'ព័ត៌មានមុន',
            nextSlideMessage: 'ព័ត៌មានបន្ទាប់',
            paginationBulletMessage: 'ទៅកាន់ព័ត៌មានទី {{index}}',
            slideLabelMessage: '{{index}} / {{slidesLength}}',
        },
        on: { init: syncSlides, slideChange: syncSlides },
    });

    const updatePlayback = () => {
        if (!multiple) return;
        if (paused || focused || hovered || document.hidden) swiper.autoplay.stop();
        else swiper.autoplay.start();
        pauseButton.setAttribute('aria-pressed', String(paused));
        pauseButton.setAttribute('aria-label', paused ? 'បន្តការប្ដូរព័ត៌មានដោយស្វ័យប្រវត្តិ' : 'ផ្អាកការប្ដូរព័ត៌មានដោយស្វ័យប្រវត្តិ');
        pauseButton.querySelector('[data-pause-icon]').hidden = paused;
        pauseButton.querySelector('[data-play-icon]').hidden = !paused;
    };

    pauseButton?.addEventListener('click', () => { paused = !paused; updatePlayback(); });
    el.addEventListener('focusin', () => { focused = true; updatePlayback(); });
    el.addEventListener('focusout', (event) => {
        focused = el.contains(event.relatedTarget);
        updatePlayback();
    });
    el.addEventListener('mouseenter', () => { hovered = true; updatePlayback(); });
    el.addEventListener('mouseleave', () => { hovered = false; updatePlayback(); });
    document.addEventListener('visibilitychange', updatePlayback);
    reducedMotion.addEventListener('change', () => {
        paused = reducedMotion.matches;
        swiper.params.speed = reducedMotion.matches ? 0 : 750;
        updatePlayback();
    });
    updatePlayback();
}
