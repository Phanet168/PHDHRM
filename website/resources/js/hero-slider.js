import { Swiper } from 'swiper';
import { Autoplay, Navigation, Pagination } from 'swiper/modules';

export function initHeroSlider() {
    const el = document.querySelector('.hero-swiper');
    if (!el) return;

    const slideCount = el.querySelectorAll('.swiper-slide').length;

    new Swiper(el, {
        modules: [Autoplay, Navigation, Pagination],
        direction: 'horizontal',
        effect: 'slide',
        speed: 900,
        loop: slideCount > 1,
        grabCursor: true,
        autoplay: slideCount > 1 ? {
            delay: 5500,
            disableOnInteraction: false,
            pauseOnMouseEnter: true,
        } : false,
        pagination: {
            el: '.hero-swiper-pagination',
            clickable: true,
        },
        navigation: {
            prevEl: '.hero-swiper-prev',
            nextEl: '.hero-swiper-next',
        },
    });
}
