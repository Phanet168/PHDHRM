// Keeps the public top bar + main menu pinned while scrolling.
// The pinned height is published as --public-sticky-offset so sticky sidebars and
// in-page anchors can clear the header (CSS holds a fallback until this runs).
export function initStickyHeader() {
    const header = document.querySelector('[data-sticky-header]');
    if (!header) return;

    const root = document.documentElement;
    const syncOffset = () => root.style.setProperty('--public-sticky-offset', `${Math.ceil(header.getBoundingClientRect().height)}px`);
    const syncShadow = () => header.classList.toggle('is-stuck', window.scrollY > 0);

    new ResizeObserver(syncOffset).observe(header);
    window.addEventListener('scroll', syncShadow, { passive: true });
    syncOffset();
    syncShadow();
}
