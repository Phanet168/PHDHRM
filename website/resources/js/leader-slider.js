import { Swiper } from 'swiper';
import { A11y, Autoplay, Navigation, Pagination } from 'swiper/modules';

export function initLeaderSlider() {
    const el = document.querySelector('.leader-recommendation-swiper');
    if (!el) return;

    const slideCount = el.querySelectorAll('.swiper-slide').length;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    new Swiper(el, {
        modules: [A11y, Autoplay, Navigation, Pagination],
        slidesPerView: 1,
        speed: reducedMotion ? 0 : 700,
        rewind: true,
        grabCursor: slideCount > 1,
        watchOverflow: true,
        autoplay: slideCount > 1 && !reducedMotion ? {
            delay: 5500,
            disableOnInteraction: false,
            pauseOnMouseEnter: true,
        } : false,
        navigation: {
            prevEl: '.leader-slider-prev',
            nextEl: '.leader-slider-next',
        },
        pagination: {
            el: '.leader-slider-pagination',
            clickable: true,
            bulletElement: 'button',
        },
        a11y: {
            prevSlideMessage: 'អនុសាសន៍មុន',
            nextSlideMessage: 'អនុសាសន៍បន្ទាប់',
            paginationBulletMessage: 'ទៅកាន់ក្រុមអនុសាសន៍ទី {{index}}',
        },
    });
}
