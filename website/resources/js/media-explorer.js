// Windows-Explorer-style behaviors for the admin Media Library: selection
// (checkbox, ctrl/cmd-click, shift-range, ctrl/cmd+A), a cut/copy/paste
// clipboard (persisted in localStorage so it survives the full-page
// navigations this Blade app uses), drag-and-drop move, a single shared
// floating context menu (reusing each card's own permission-gated menu
// markup, just repositioned), and toast notifications.
export function registerMediaExplorer(Alpine) {
    Alpine.data('mediaExplorer', (config = {}) => ({
        dragOver: false,
        selected: [],
        lastClickedKey: null,
        dropTarget: null,
        bulkMoveFolderId: '',
        clipboard: null,
        openMenuKey: null,
        treeDrawerOpen: false,
        detailsSheetOpen: false,
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content,
        bulkActionUrl: config.bulkActionUrl,
        currentFolderId: config.currentFolderId ?? null,
        referencesUrlTemplate: config.referencesUrlTemplate,
        usageReferences: null,
        usageLoading: false,
        usageForKey: null,
        previewItems: config.previewItems || [],
        previewIndex: null,

        init() {
            this.loadClipboard();
            this.showQueuedToast();
        },

        // ---- selection ----
        key(type, id) {
            return `${type}:${id}`;
        },

        isSelected(type, id) {
            return this.selected.includes(this.key(type, id));
        },

        allKeys() {
            return Array.from(document.querySelectorAll('[data-select-key]')).map((el) => el.dataset.selectKey);
        },

        toggleSelect(type, id) {
            const k = this.key(type, id);
            const idx = this.selected.indexOf(k);
            if (idx === -1) this.selected.push(k); else this.selected.splice(idx, 1);
            this.lastClickedKey = k;
        },

        selectOnly(type, id) {
            this.selected = [this.key(type, id)];
            this.lastClickedKey = this.selected[0];
        },

        selectRange(type, id) {
            const keys = this.allKeys();
            const targetKey = this.key(type, id);
            const fromIdx = keys.indexOf(this.lastClickedKey);
            const toIdx = keys.indexOf(targetKey);
            if (fromIdx === -1 || toIdx === -1) {
                this.selectOnly(type, id);
                return;
            }
            const [start, end] = fromIdx < toIdx ? [fromIdx, toIdx] : [toIdx, fromIdx];
            this.selected = Array.from(new Set(keys.slice(start, end + 1)));
        },

        handleItemClick(event, type, id) {
            if (event.shiftKey) { event.preventDefault(); this.selectRange(type, id); return; }
            if (event.ctrlKey || event.metaKey) { event.preventDefault(); this.toggleSelect(type, id); return; }
            this.selectOnly(type, id);
            this.syncUsagePanel();
        },

        // ---- "Used In" usage info (Details panel) ----
        // Only ever fetched for a single selected FILE (never bulk, never for folders — a
        // folder's own contents already show a count in its details), and only once per
        // selection (usageForKey guards a repeat click on the same item from re-fetching).
        syncUsagePanel() {
            if (this.selected.length !== 1) {
                this.usageReferences = null;
                this.usageForKey = null;
                return;
            }
            const { type, id } = this.parseKey(this.selected[0]);
            if (type !== 'media') {
                this.usageReferences = null;
                this.usageForKey = null;
                return;
            }
            this.loadUsage(id);
        },

        async loadUsage(mediaId) {
            const key = this.key('media', mediaId);
            if (this.usageForKey === key) return;
            this.usageForKey = key;
            this.usageReferences = null;
            if (!this.referencesUrlTemplate) return;
            this.usageLoading = true;
            try {
                const url = this.referencesUrlTemplate.replace('__ID__', mediaId);
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                const data = await response.json();
                this.usageReferences = data.references || [];
            } catch (e) {
                this.usageReferences = [];
            } finally {
                this.usageLoading = false;
            }
        },

        openItem(type, id, url) {
            if (type === 'folder') {
                window.location.href = url;
            } else {
                this.openPreview(id);
            }
        },

        // ---- Preview modal (image/video large view with prev/next, spec §12) ----
        get previewItem() {
            return this.previewIndex === null ? null : this.previewItems[this.previewIndex];
        },

        openPreview(mediaId) {
            const idx = this.previewItems.findIndex((it) => it.id === mediaId);
            if (idx === -1) { window.open(this.previewItems.find((it) => it.id === mediaId)?.url, '_blank'); return; }
            this.previewIndex = idx;
        },

        closePreview() {
            this.previewIndex = null;
        },

        previewNext() {
            if (this.previewIndex === null || !this.previewItems.length) return;
            this.previewIndex = (this.previewIndex + 1) % this.previewItems.length;
        },

        previewPrev() {
            if (this.previewIndex === null || !this.previewItems.length) return;
            this.previewIndex = (this.previewIndex - 1 + this.previewItems.length) % this.previewItems.length;
        },

        clearSelection() {
            this.selected = [];
            this.bulkMoveFolderId = '';
            this.usageReferences = null;
            this.usageForKey = null;
        },

        selectAll() {
            this.selected = this.allKeys();
            this.usageReferences = null;
            this.usageForKey = null;
        },

        handleGlobalKeydown(event) {
            const tag = event.target.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA') return;

            if (this.previewIndex !== null) {
                if (event.key === 'Escape') { this.closePreview(); return; }
                if (event.key === 'ArrowRight') { event.preventDefault(); this.previewNext(); return; }
                if (event.key === 'ArrowLeft') { event.preventDefault(); this.previewPrev(); return; }
                return;
            }

            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'a') {
                event.preventDefault();
                this.selectAll();
            } else if (event.key === 'Escape') {
                this.clearSelection();
                this.closeMenu();
            } else if (event.key === 'Delete' && this.selected.length) {
                this.bulkDeleteSelected();
            }
        },

        get selectionCount() {
            return this.selected.length;
        },

        parseKey(k) {
            const [type, id] = k.split(':');
            return { type, id: parseInt(id, 10) };
        },

        selectedItems() {
            return this.selected.map((k) => this.parseKey(k));
        },

        isDetailsFor(type, id) {
            return this.selected.length === 1 && this.selected[0] === this.key(type, id);
        },

        selectedTotalSize() {
            let total = 0;
            document.querySelectorAll('[data-select-key]').forEach((el) => {
                if (this.selected.includes(el.dataset.selectKey)) {
                    total += parseInt(el.dataset.sizeBytes || '0', 10);
                }
            });
            return total;
        },

        formatBytes(bytes) {
            const units = ['B', 'KB', 'MB', 'GB'];
            let i = 0;
            let size = bytes;
            while (size >= 1024 && i < units.length - 1) { size /= 1024; i++; }
            return Math.round(size * 10) / 10 + ' ' + units[i];
        },

        // ---- drag & drop ----
        dragStart(event, type, id) {
            const items = this.isSelected(type, id) && this.selected.length > 1
                ? this.selectedItems()
                : [{ type, id }];
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('application/json', JSON.stringify(items));
        },

        dragOverFolder(event, folderId) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            this.dropTarget = folderId ?? 'root';
        },

        dragLeaveFolder(folderId) {
            if (this.dropTarget === (folderId ?? 'root')) this.dropTarget = null;
        },

        isDropTarget(folderId) {
            return this.dropTarget === (folderId ?? 'root');
        },

        dropOnFolder(event, folderId) {
            event.preventDefault();
            this.dropTarget = null;
            let items = [];
            try {
                items = JSON.parse(event.dataTransfer.getData('application/json'));
            } catch (e) {
                return;
            }
            if (!items.length) return;
            this.sendBulk('move', items, folderId);
        },

        // ---- backend calls ----
        async sendBulk(action, items, folderId = null, force = false) {
            let response;
            try {
                response = await fetch(this.bulkActionUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({ action, items, folder_id: folderId, force }),
                });
            } catch (e) {
                alert('មិនអាចភ្ជាប់ទៅម៉ាស៊ីនមេបានទេ។');
                return;
            }

            const result = await response.json().catch(() => null);

            if (!response.ok) {
                alert((result && result.message) || 'មានបញ្ហាកើតឡើង។');
                return;
            }

            if (action === 'delete' && result?.blocked?.length) {
                alert(
                    `មានធាតុ ${result.blocked.length} មិនអាចលុបបានទេ ព្រោះកំពុងត្រូវបានប្រើប្រាស់ ឬថតមិនទទេ។ `
                    + 'សូមលុបធាតុទាំងនោះម្តងមួយៗ ដើម្បីមើលព័ត៌មានលម្អិត។'
                );
            }

            const messages = { move: 'បានផ្លាស់ទីដោយជោគជ័យ។', duplicate: 'បានចម្លងដោយជោគជ័យ។', delete: 'បានទុកក្នុងធុងសម្រាមដោយជោគជ័យ។' };
            if (result?.succeeded) this.queueToast(messages[action] || 'ជោគជ័យ។');
            window.location.reload();
        },

        bulkDeleteSelected() {
            if (!this.selected.length) return;
            if (!confirm(`ទុកធាតុដែលបានជ្រើសរើស ${this.selected.length} ក្នុងធុងសម្រាម?`)) return;
            this.sendBulk('delete', this.selectedItems());
        },

        bulkMoveSelected() {
            if (!this.selected.length || this.bulkMoveFolderId === '') return;
            const folderId = this.bulkMoveFolderId === '0' ? null : parseInt(this.bulkMoveFolderId, 10);
            this.sendBulk('move', this.selectedItems(), folderId);
        },

        // ---- cut / copy / paste ----
        loadClipboard() {
            try {
                const raw = localStorage.getItem('media-clipboard');
                this.clipboard = raw ? JSON.parse(raw) : null;
            } catch (e) {
                this.clipboard = null;
            }
        },

        saveClipboard() {
            try {
                if (this.clipboard) localStorage.setItem('media-clipboard', JSON.stringify(this.clipboard));
                else localStorage.removeItem('media-clipboard');
            } catch (e) {
                // Storage unavailable (private mode etc.) — clipboard just won't persist across reloads.
            }
        },

        cutSelected() {
            if (!this.selected.length) return;
            this.clipboard = { mode: 'cut', items: this.selectedItems() };
            this.saveClipboard();
            this.showToast('បានកាត់ធាតុ។ រុករកទៅទីតាំងគោលដៅ រួចចុច "បិទភ្ជាប់"។');
        },

        copySelected() {
            if (!this.selected.length) return;
            this.clipboard = { mode: 'copy', items: this.selectedItems() };
            this.saveClipboard();
            this.showToast('បានចម្លងធាតុ។ រុករកទៅទីតាំងគោលដៅ រួចចុច "បិទភ្ជាប់"។');
        },

        get canPaste() {
            return !!(this.clipboard && this.clipboard.items && this.clipboard.items.length);
        },

        pasteClipboard() {
            if (!this.canPaste) return;
            const { mode, items } = this.clipboard;
            if (mode === 'cut') { this.clipboard = null; this.saveClipboard(); }
            this.sendBulk(mode === 'cut' ? 'move' : 'duplicate', items, this.currentFolderId);
        },

        isCut(type, id) {
            return !!(this.clipboard && this.clipboard.mode === 'cut'
                && this.clipboard.items.some((it) => it.type === type && it.id === id));
        },

        // ---- toasts ----
        queueToast(message) {
            try { sessionStorage.setItem('media-toast', message); } catch (e) { /* ignore */ }
        },

        showQueuedToast() {
            try {
                const msg = sessionStorage.getItem('media-toast');
                if (msg) { sessionStorage.removeItem('media-toast'); this.showToast(msg); }
            } catch (e) { /* ignore */ }
        },

        showToast(message) {
            let container = document.getElementById('media-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'media-toast-container';
                document.body.appendChild(container);
            }
            const el = document.createElement('div');
            el.className = 'media-toast';
            el.textContent = message;
            container.appendChild(el);
            setTimeout(() => el.remove(), 3200);
        },

        // ---- navigation ----
        goBack() { window.history.back(); },
        goForward() { window.history.forward(); },

        // ---- shared floating context menu ----
        toggleMenu(key) {
            if (this.openMenuKey === key) { this.closeMenu(); return; }
            this.openMenuKey = key;
            this.$nextTick(() => this.resetMenuPosition(key));
        },

        closeMenu() {
            if (this.openMenuKey) this.resetMenuPosition(this.openMenuKey);
            this.openMenuKey = null;
        },

        resetMenuPosition(key) {
            const menu = document.querySelector(`[data-menu-for="${window.CSS.escape(key)}"]`);
            if (menu) { menu.style.position = ''; menu.style.left = ''; menu.style.top = ''; }
        },

        openContextMenu(event, type, id) {
            event.preventDefault();
            const key = this.key(type, id);
            if (!this.isSelected(type, id)) { this.selectOnly(type, id); this.syncUsagePanel(); }
            this.openMenuKey = key;
            this.$nextTick(() => {
                const menu = document.querySelector(`[data-menu-for="${window.CSS.escape(key)}"]`);
                if (!menu) return;
                menu.style.position = 'fixed';
                const maxLeft = window.innerWidth - menu.offsetWidth - 8;
                const maxTop = window.innerHeight - menu.offsetHeight - 8;
                menu.style.left = Math.max(4, Math.min(event.clientX, maxLeft)) + 'px';
                menu.style.top = Math.max(4, Math.min(event.clientY, maxTop)) + 'px';
            });
        },

        // ---- mobile drawers ----
        toggleTreeDrawer() { this.treeDrawerOpen = !this.treeDrawerOpen; },
        toggleDetailsSheet() { this.detailsSheetOpen = !this.detailsSheetOpen; },

        copyUrl(url) {
            navigator.clipboard.writeText(url).then(() => this.showToast('បានចម្លងតំណ។'));
        },
    }));
}
