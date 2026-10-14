import Sortable from 'sortablejs';

export function initMenuSort() {
    const root = document.querySelector('[data-menu-sort-root]');
    if (!root) return;

    const reorderUrl = root.dataset.reorderUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    const lists = root.querySelectorAll('.menu-tree');

    lists.forEach((list) => {
        new Sortable(list, {
            group: 'menu-items',
            animation: 150,
            handle: '.menu-drag-handle',
            fallbackOnBody: true,
            swapThreshold: 0.65,
            onEnd: save,
        });
    });

    function save() {
        const items = [];

        root.querySelectorAll('.menu-tree').forEach((list) => {
            const parentId = list.dataset.parentId || null;
            [...list.children].forEach((li, index) => {
                items.push({
                    id: li.dataset.id,
                    parent_id: parentId,
                    sort_order: index,
                });
            });
        });

        fetch(reorderUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
            body: JSON.stringify({ items }),
        });
    }
}
