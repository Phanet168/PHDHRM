// GLightbox is only needed on the public article detail page when it actually
// has a gallery, so it is fetched on demand instead of bundled site-wide.
export function initContentGallery() {
    const hasGallery = document.querySelector('.content-gallery-link');
    const featuredImage = document.querySelector('.article-featured-image[href]');
    if (!hasGallery && !featuredImage) return;

    import('glightbox').then(({ default: GLightbox }) => {
        if (hasGallery) {
            GLightbox({
                selector: '.content-gallery-link',
                touchNavigation: true,
                loop: true,
                zoomable: true,
            });
        }

        if (featuredImage) {
            GLightbox({
                selector: '.article-featured-image',
                touchNavigation: true,
                zoomable: true,
            });
        }
    });
}
