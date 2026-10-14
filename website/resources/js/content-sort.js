import Sortable from 'sortablejs';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

function post(url, body) {
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            Accept: 'application/json',
        },
        body: JSON.stringify(body),
    });
}

export function initContentSort() {
    const blockRoot = document.querySelector('[data-block-sort-root]');
    if (blockRoot) {
        const list = blockRoot.querySelector('.content-block-list');
        new Sortable(list, {
            animation: 150,
            handle: '.block-drag-handle',
            fallbackOnBody: true,
            swapThreshold: 0.65,
            onEnd: () => {
                const items = [...list.children].map((li, index) => ({ id: li.dataset.id, sort_order: index }));
                post(blockRoot.dataset.reorderUrl, { items });
            },
        });
    }

    const galleryRoot = document.querySelector('[data-gallery-sort-root]');
    if (galleryRoot) {
        const list = galleryRoot.querySelector('.content-gallery-list');
        new Sortable(list, {
            animation: 150,
            handle: '.gallery-drag-handle',
            fallbackOnBody: true,
            swapThreshold: 0.65,
            onEnd: () => {
                const items = [...list.children].map((el, index) => ({ id: el.dataset.id, sort_order: index }));
                post(galleryRoot.dataset.reorderUrl, { items });
            },
        });
    }

    const attachmentRoot = document.querySelector('[data-attachment-sort-root]');
    if (attachmentRoot) {
        const list = attachmentRoot.querySelector('.news-attachment-list');
        new Sortable(list, {
            animation: 150,
            handle: '.attachment-drag-handle',
            fallbackOnBody: true,
            swapThreshold: 0.65,
            onEnd: () => {
                const items = [...list.children].map((el, index) => ({ id: el.dataset.id, sort_order: index }));
                post(attachmentRoot.dataset.reorderUrl, { items });
            },
        });
    }
}
