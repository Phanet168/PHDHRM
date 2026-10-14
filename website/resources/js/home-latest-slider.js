import { Swiper } from 'swiper';
import { A11y, Autoplay, Navigation, Pagination } from 'swiper/modules';

export function initHomeLatestSlider() {
    const el = document.querySelector('.home-latest-swiper');
    if (!el) return;

    const slideCount = el.querySelectorAll('.swiper-slide').length;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    new Swiper(el, {
        modules: [A11y, Autoplay, Navigation, Pagination],
        slidesPerView: 1.15,
        spaceBetween: 14,
        speed: reducedMotion ? 0 : 700,
        rewind: slideCount > 1,
        grabCursor: slideCount > 1,
        watchOverflow: true,
        autoplay: slideCount > 1 && !reducedMotion ? {
            delay: 5000,
            disableOnInteraction: false,
            pauseOnMouseEnter: true,
        } : false,
        navigation: {
            prevEl: '.home-latest-prev',
            nextEl: '.home-latest-next',
        },
        pagination: {
            el: '.home-latest-pagination',
            clickable: true,
            bulletElement: 'button',
        },
        // Swiper breakpoints are raw pixels, not rem — 480/576/768/1024/1280
        // match this project's 30/36/48/64/80rem breakpoint scale.
        breakpoints: {
            480: { slidesPerView: 1.3 },
            576: { slidesPerView: 1.4 },
            768: { slidesPerView: 2.6, spaceBetween: 16 },
            1024: { slidesPerView: 3, spaceBetween: 16 },
            1280: { slidesPerView: 4, spaceBetween: 16 },
        },
        a11y: {
            prevSlideMessage: 'មាតិកាមុន',
            nextSlideMessage: 'មាតិកាបន្ទាប់',
            paginationBulletMessage: 'ទៅកាន់ក្រុមមាតិកាទី {{index}}',
        },
    });
}
